<?php

namespace App\Http\Controllers;

use App\Enums\UserVerificationCategory;
use App\Enums\UserVerificationStatus;
use App\Http\Requests\Verification\StoreUserVerificationRequest;
use App\Models\UserVerification;
use App\Services\Kyc\KycCaseIntakeService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class UserVerificationController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', UserVerification::class);

        $items = $request->user()
            ->userVerifications()
            ->with('freelancerCredential:id,title')
            ->latest()
            ->get()
            ->map(fn (UserVerification $v) => [
                'id' => $v->id,
                'category' => $v->category->value,
                'category_label' => $this->categoryLabel($v->category),
                'status' => $v->status->value,
                'submitted_at' => $v->submitted_at?->timezone('Africa/Lagos')->toIso8601String(),
                'credential_title' => $v->freelancerCredential?->title,
            ]);

        return Inertia::render('Verifications/Index', [
            'items' => $items,
        ]);
    }

    public function store(StoreUserVerificationRequest $request, KycCaseIntakeService $kycIntake): RedirectResponse
    {
        $this->authorize('create', UserVerification::class);

        $data = $request->validated();
        $category = UserVerificationCategory::from($data['category']);

        $pendingExists = UserVerification::query()
            ->where('user_id', $request->user()->id)
            ->where('category', $category)
            ->whereIn('status', [UserVerificationStatus::Pending, UserVerificationStatus::InReview])
            ->when(
                $category === UserVerificationCategory::Qualification,
                fn ($q) => $q->where('freelancer_credential_id', $data['freelancer_credential_id'] ?? 0),
                fn ($q) => $q->whereNull('freelancer_credential_id'),
            )
            ->exists();

        if ($pendingExists) {
            return back()->withErrors([
                'category' => __('You already have a submission in progress for this category.'),
            ]);
        }

        $userId = $request->user()->id;
        $disk = 'local';
        $dir = "user-verifications/{$userId}";

        $paths = [];
        $metadata = [];

        if ($category === UserVerificationCategory::LivePresence) {
            $file = $request->file('live_photo');
            $path = $file->store($dir, $disk);
            $paths[] = $path;
            $metadata = [
                'kind' => 'selfie_with_id',
                'original_name' => $file->getClientOriginalName(),
                'note' => __('Hold your approved ID beside your face in good light. One clear photo is enough for review.'),
            ];
        } else {
            $labels = $data['document_labels'];
            $rawFiles = $request->file('document_files');
            $fileList = is_array($rawFiles) ? array_values($rawFiles) : [$rawFiles];
            $metadata = [
                'id_type' => $data['id_type'] ?? null,
                'identifier_number' => $data['identifier_number'] ?? null,
                'cac_number' => $data['cac_number'] ?? null,
                'registered_business_name' => $data['registered_business_name'] ?? null,
                'documents' => [],
            ];
            foreach ($fileList as $i => $file) {
                if ($file === null) {
                    continue;
                }
                $path = $file->store($dir, $disk);
                $paths[] = $path;
                $metadata['documents'][] = [
                    'label' => $labels[$i] ?? 'Document',
                    'path' => $path,
                    'original_name' => $file->getClientOriginalName(),
                ];
            }
        }

        $verification = UserVerification::query()->create([
            'user_id' => $request->user()->id,
            'category' => $category,
            'target_tier' => $this->targetTierFor($category, $data['id_type'] ?? null),
            'freelancer_credential_id' => $data['freelancer_credential_id'] ?? null,
            'status' => UserVerificationStatus::Pending,
            'document_paths' => $paths,
            'metadata' => $metadata,
            'queue_reason' => $this->queueReasonFor($category, $request->user()),
            'attempt_count' => UserVerification::query()
                ->where('user_id', $request->user()->id)
                ->where('category', $category)
                ->count() + 1,
            'submitted_at' => now(),
        ]);

        $kycIntake->createFromVerification($verification, $verification->queue_reason ?: 'manual_review');

        return redirect()
            ->route('verifications.index')
            ->with('success', __('Submission received — our team will review shortly.'));
    }

    protected function categoryLabel(UserVerificationCategory $c): string
    {
        return match ($c) {
            UserVerificationCategory::Identity => __('Government ID'),
            UserVerificationCategory::Address => __('Proof of address'),
            UserVerificationCategory::Qualification => __('Qualification'),
            UserVerificationCategory::Business => __('Business verification'),
            UserVerificationCategory::LivePresence => __('Selfie + ID'),
        };
    }

    protected function targetTierFor(UserVerificationCategory $c, ?string $idType = null): int
    {
        if ($c === UserVerificationCategory::Identity && $idType === 'bvn') {
            return 4;
        }

        return match ($c) {
            UserVerificationCategory::Identity => 2,
            UserVerificationCategory::Address => 3,
            UserVerificationCategory::LivePresence => 4,
            UserVerificationCategory::Qualification => 4,
            UserVerificationCategory::Business => 5,
        };
    }

    protected function queueReasonFor(UserVerificationCategory $c, $user): string
    {
        if ($c === UserVerificationCategory::Identity) {
            $identifier = (string) request('identifier_number');
            $duplicate = $identifier !== '' && UserVerification::query()
                ->where('user_id', '<>', $user->id)
                ->where('metadata->identifier_number', $identifier)
                ->where('status', UserVerificationStatus::Approved)
                ->exists();

            return $duplicate ? 'duplicate_identity' : 'manual_escalation';
        }

        if ($c === UserVerificationCategory::Business) {
            return 'cac_review_required';
        }

        return $c === UserVerificationCategory::LivePresence ? 'liveness_review' : 'manual_review';
    }
}
