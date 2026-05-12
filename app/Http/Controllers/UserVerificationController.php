<?php

namespace App\Http\Controllers;

use App\Enums\UserVerificationCategory;
use App\Enums\UserVerificationStatus;
use App\Http\Requests\Verification\StoreUserVerificationRequest;
use App\Models\UserVerification;
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
                'status' => $v->status->value,
                'submitted_at' => $v->submitted_at?->timezone('Africa/Lagos')->toIso8601String(),
                'credential_title' => $v->freelancerCredential?->title,
            ]);

        return Inertia::render('Verifications/Index', [
            'items' => $items,
        ]);
    }

    public function store(StoreUserVerificationRequest $request): RedirectResponse
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

        UserVerification::query()->create([
            'user_id' => $request->user()->id,
            'category' => $category,
            'freelancer_credential_id' => $data['freelancer_credential_id'] ?? null,
            'status' => UserVerificationStatus::Pending,
            'document_paths' => $data['document_paths'] ?? null,
            'metadata' => $data['metadata'] ?? null,
            'submitted_at' => now(),
        ]);

        return redirect()
            ->route('verifications.index')
            ->with('success', __('Submission received — our team will review shortly.'));
    }
}
