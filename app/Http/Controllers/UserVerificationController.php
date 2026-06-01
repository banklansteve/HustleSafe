<?php

namespace App\Http\Controllers;

use App\Enums\UserVerificationCategory;
use App\Enums\UserVerificationStatus;
use App\Http\Requests\Verification\StoreUserVerificationRequest;
use App\Models\UserVerification;
use App\Services\Kyc\KycCaseIntakeService;
use App\Services\Verification\IdentityDocumentUniquenessService;
use App\Services\Verification\UserVerificationCatalogService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class UserVerificationController extends Controller
{
    public function index(Request $request, UserVerificationCatalogService $catalog): Response
    {
        $this->authorize('viewAny', UserVerification::class);

        return Inertia::render('Verifications/Index', $catalog->forUser($request->user()));
    }

    public function store(
        StoreUserVerificationRequest $request,
        KycCaseIntakeService $kycIntake,
        UserVerificationCatalogService $catalog,
        IdentityDocumentUniquenessService $identityUniqueness,
    ): RedirectResponse
    {
        $this->authorize('create', UserVerification::class);

        $user = $request->user();
        $data = $request->validated();
        $category = UserVerificationCategory::from($data['category']);

        $userId = $user->id;
        $disk = 'local';
        $dir = "user-verifications/{$userId}";

        $this->assertCanSubmit($user, $category, $catalog);

        $pendingExists = UserVerification::query()
            ->where('user_id', $user->id)
            ->where('category', $category)
            ->whereIn('status', [UserVerificationStatus::Pending, UserVerificationStatus::InReview])
            ->exists();

        if ($pendingExists) {
            return back()->withErrors([
                'category' => __('You already have a submission in progress for this category.'),
            ]);
        }

        [$paths, $metadata] = $this->buildSubmissionPayload($request, $category, $data, $dir, $disk);
        $duplicateContext = $this->resolveDuplicateContext($identityUniqueness, $user, $category, $data);
        if ($duplicateContext !== null) {
            $metadata = array_merge($metadata, $duplicateContext['metadata']);
        }

        $queueReason = $duplicateContext !== null
            ? 'duplicate_identity'
            : $this->queueReasonFor($category, $user);

        $verification = UserVerification::query()->create([
            'user_id' => $userId,
            'submitted_by' => $userId,
            'category' => $category,
            'verification_type' => $this->verificationTypeFor($category),
            'target_tier' => $this->targetTierFor($category),
            'status' => UserVerificationStatus::Pending,
            'document_paths' => $paths,
            'metadata' => $metadata,
            'encrypted_identifier' => $category === UserVerificationCategory::Bvn
                ? Crypt::encryptString((string) $data['identifier_number'])
                : null,
            'queue_reason' => $queueReason,
            'attempt_count' => UserVerification::query()
                ->where('user_id', $userId)
                ->where('category', $category)
                ->count() + 1,
            'submitted_at' => now(),
        ]);

        $kycIntake->createFromVerification($verification, $verification->queue_reason ?: 'manual_review');

        try {
            $assignment = app(\App\Services\Verification\VerificationQueueAssignmentService::class);
            $assignment->assignNextAvailable($verification->fresh(['user', 'assignedStaff', 'user.role']));
            $assignment->broadcastUpdate($verification->fresh(['user', 'assignedStaff', 'user.role']), 'created');
        } catch (\Throwable $e) {
            report($e);
        }

        app(\App\Services\Platform\PlatformSlaService::class)->start(
            'kyc_verification',
            $verification,
            null,
            $user,
            [
                'subject_label' => ($user->name ?? 'User').' · '.$verification->verification_type,
                'user_id' => $user->id,
            ],
            $verification->submitted_at,
        );

        return redirect()
            ->route('verifications.index')
            ->with('success', __('Submission received — our team will review shortly. You will be notified in-app and by email when a decision is made.'))
            ->with('sla_expectation', app(\App\Services\Platform\PlatformSlaService::class)->userExpectationMessage('kyc_verification'));
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{0: list<string>, 1: array<string, mixed>}
     */
    protected function buildSubmissionPayload(StoreUserVerificationRequest $request, UserVerificationCategory $category, array $data, string $dir, string $disk): array
    {
        $paths = [];
        $metadata = [];

        if (in_array($category, [UserVerificationCategory::Nin, UserVerificationCategory::Bvn], true)) {
            $metadata = [
                'identifier_number' => $category === UserVerificationCategory::Bvn ? null : $data['identifier_number'],
                'identifier_masked' => $this->maskIdentifier((string) $data['identifier_number']),
                'kind' => $category->value,
            ];

            return [$paths, $metadata];
        }

        if ($category === UserVerificationCategory::LivePresence) {
            $file = $request->file('live_photo');
            $path = $file->store($dir, $disk);
            $paths[] = $path;

            return [$paths, [
                'kind' => 'selfie_with_id',
                'original_name' => $file->getClientOriginalName(),
                'note' => __('Selfie with government ID held beside the face.'),
            ]];
        }

        if ($category === UserVerificationCategory::IdentityAddress) {
            $metadata = [
                'id_type' => $data['id_type'],
                'identifier_number' => $data['identifier_number'],
                'confirmed_address' => $data['confirmed_address'],
                'documents' => [],
            ];

            $idFile = $request->file('id_document');
            $idPath = $idFile->store($dir, $disk);
            $paths[] = $idPath;
            $metadata['documents'][] = [
                'role' => 'identity',
                'label' => $this->idDocumentLabel((string) $data['id_type']),
                'path' => $idPath,
                'original_name' => $idFile->getClientOriginalName(),
            ];

            foreach ($request->file('address_documents', []) as $index => $file) {
                $path = $file->store($dir, $disk);
                $paths[] = $path;
                $metadata['documents'][] = [
                    'role' => 'address',
                    'label' => __('Proof of address :n', ['n' => $index + 1]),
                    'path' => $path,
                    'original_name' => $file->getClientOriginalName(),
                ];
            }

            $extraLabels = $request->input('additional_id_labels', []);
            foreach ($request->file('additional_id_documents', []) as $index => $file) {
                $path = $file->store($dir, $disk);
                $paths[] = $path;
                $metadata['documents'][] = [
                    'role' => 'identity_extra',
                    'label' => (string) ($extraLabels[$index] ?? __('Additional ID :n', ['n' => $index + 1])),
                    'path' => $path,
                    'original_name' => $file->getClientOriginalName(),
                ];
            }

            return [$paths, $metadata];
        }

        if (in_array($category, [UserVerificationCategory::Cac, UserVerificationCategory::Tin], true)) {
            $metadata = [
                'kind' => $category->value,
                'identifier_number' => $data['identifier_number'],
                'cac_number' => $category === UserVerificationCategory::Cac ? $data['identifier_number'] : null,
                'registered_business_name' => $data['registered_business_name'] ?? null,
                'documents' => [],
            ];

            $rawFiles = $request->file('document_files');
            if ($rawFiles !== null) {
                $labels = $request->input('document_labels', []);
                $fileList = is_array($rawFiles) ? array_values($rawFiles) : [$rawFiles];
                foreach ($fileList as $i => $file) {
                    if ($file === null) {
                        continue;
                    }
                    $path = $file->store($dir, $disk);
                    $paths[] = $path;
                    $metadata['documents'][] = [
                        'label' => $labels[$i] ?? __('Supporting document'),
                        'path' => $path,
                        'original_name' => $file->getClientOriginalName(),
                    ];
                }
            }

            return [$paths, $metadata];
        }

        if ($category === UserVerificationCategory::ProfessionalCertificate) {
            $metadata = ['entries' => [], 'documents' => []];
            foreach ($data['professional_entries'] as $index => $entry) {
                $row = [
                    'what_submitting' => $entry['what_submitting'],
                    'credential_identification' => $entry['credential_identification'] ?? null,
                    'awarding_body' => $entry['awarding_body'],
                    'year' => (int) $entry['year'],
                ];
                $file = $request->file("professional_entries.{$index}.file");
                if ($file !== null) {
                    $path = $file->store($dir, $disk);
                    $paths[] = $path;
                    $row['path'] = $path;
                    $row['original_name'] = $file->getClientOriginalName();
                    $metadata['documents'][] = [
                        'label' => $entry['what_submitting'],
                        'path' => $path,
                        'original_name' => $file->getClientOriginalName(),
                    ];
                }
                $metadata['entries'][] = $row;
            }

            return [$paths, $metadata];
        }

        return [$paths, $metadata];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{metadata: array<string, mixed>}|null
     */
    protected function resolveDuplicateContext(
        IdentityDocumentUniquenessService $identityUniqueness,
        $user,
        UserVerificationCategory $category,
        array $data,
    ): ?array {
        [$documentKind, $identifier] = $this->identityDocumentForDuplicateCheck($category, $data);
        if ($documentKind === null || $identifier === '') {
            return null;
        }

        $accounts = $identityUniqueness->conflictingAccounts($user, $documentKind, $identifier);
        if ($accounts === []) {
            return null;
        }

        $documentLabel = match ($identityUniqueness->normalizeKind($documentKind)) {
            'nin' => 'NIN',
            'bvn' => 'BVN',
            'passport' => 'passport number',
            'national_id' => 'national ID card number',
            'drivers_licence' => 'driver\'s licence number',
            'voters_card' => 'voter\'s card number',
            default => 'identity document',
        };

        return [
            'metadata' => [
                'duplicate_accounts' => $accounts,
                'duplicate_document_kind' => $identityUniqueness->normalizeKind($documentKind),
                'duplicate_document_label' => $documentLabel,
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{0: ?string, 1: string}
     */
    protected function identityDocumentForDuplicateCheck(UserVerificationCategory $category, array $data): array
    {
        if (in_array($category, [UserVerificationCategory::Nin, UserVerificationCategory::Bvn], true)) {
            return [$category->value, (string) ($data['identifier_number'] ?? '')];
        }

        if ($category === UserVerificationCategory::IdentityAddress) {
            return [(string) ($data['id_type'] ?? ''), (string) ($data['identifier_number'] ?? '')];
        }

        return [null, ''];
    }

    protected function assertCanSubmit($user, UserVerificationCategory $category, UserVerificationCatalogService $catalog): void
    {
        $isFreelancer = $catalog->isFreelancer($user);
        $clientOnly = [UserVerificationCategory::Nin, UserVerificationCategory::Bvn, UserVerificationCategory::IdentityAddress];
        $freelancerOnly = [UserVerificationCategory::Cac, UserVerificationCategory::Tin, UserVerificationCategory::LivePresence, UserVerificationCategory::ProfessionalCertificate];

        if (! $isFreelancer && in_array($category, $freelancerOnly, true)) {
            throw ValidationException::withMessages(['category' => __('This verification type is not required for client accounts.')]);
        }

        if ($isFreelancer || in_array($category, $clientOnly, true)) {
            // allowed role bucket
        } else {
            throw ValidationException::withMessages(['category' => __('Unsupported verification category.')]);
        }

        $approved = UserVerification::query()
            ->where('user_id', $user->id)
            ->where('category', $category)
            ->whereIn('status', [UserVerificationStatus::Approved, UserVerificationStatus::Verified])
            ->exists();

        if (in_array($category, [UserVerificationCategory::Nin, UserVerificationCategory::Bvn, UserVerificationCategory::IdentityAddress, UserVerificationCategory::LivePresence], true) && $approved) {
            throw ValidationException::withMessages(['category' => __('This verification is already approved and cannot be submitted again.')]);
        }

        if (in_array($category, [UserVerificationCategory::Cac, UserVerificationCategory::Tin], true)) {
            $businessApproved = UserVerification::query()
                ->where('user_id', $user->id)
                ->whereIn('category', [UserVerificationCategory::Cac, UserVerificationCategory::Tin])
                ->whereIn('status', [UserVerificationStatus::Approved, UserVerificationStatus::Verified])
                ->exists();
            if ($businessApproved) {
                throw ValidationException::withMessages(['category' => __('Business verification is already approved.')]);
            }
        }

        if ($category === UserVerificationCategory::LivePresence) {
            $businessOk = UserVerification::query()
                ->where('user_id', $user->id)
                ->whereIn('category', [UserVerificationCategory::Cac, UserVerificationCategory::Tin])
                ->whereIn('status', [UserVerificationStatus::Approved, UserVerificationStatus::Verified])
                ->exists();
            if (! $businessOk) {
                throw ValidationException::withMessages(['category' => __('Complete CAC or TIN verification before submitting a selfie + ID.')]);
            }
        }
    }

    protected function targetTierFor(UserVerificationCategory $c): int
    {
        return match ($c) {
            UserVerificationCategory::Nin => 3,
            UserVerificationCategory::IdentityAddress => 2,
            UserVerificationCategory::Bvn => 4,
            default => 2,
        };
    }

    protected function queueReasonFor(UserVerificationCategory $c, $user): string
    {
        if (in_array($c, [UserVerificationCategory::Business, UserVerificationCategory::Cac, UserVerificationCategory::Tin], true)) {
            return 'cac_review_required';
        }

        return $c === UserVerificationCategory::LivePresence ? 'liveness_review' : 'manual_review';
    }

    protected function verificationTypeFor(UserVerificationCategory $c): string
    {
        return match ($c) {
            UserVerificationCategory::Cac => 'cac',
            UserVerificationCategory::Tin => 'tin',
            default => $c->value,
        };
    }

    protected function idDocumentLabel(string $idType): string
    {
        return match ($idType) {
            'passport' => __('Passport — photo page'),
            'national_id' => __('National ID card'),
            'drivers_licence' => __('Driver\'s licence — front'),
            'voters_card' => __('Voter\'s card'),
            default => __('Government ID'),
        };
    }

    protected function maskIdentifier(string $value): string
    {
        return strlen($value) <= 4 ? str_repeat('*', strlen($value)) : str_repeat('*', max(0, strlen($value) - 4)).substr($value, -4);
    }
}
