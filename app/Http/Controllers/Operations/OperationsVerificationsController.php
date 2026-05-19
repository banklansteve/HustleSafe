<?php

namespace App\Http\Controllers\Operations;

use App\Http\Controllers\Controller;
use App\Models\UserVerification;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class OperationsVerificationsController extends Controller
{
    public function index(Request $request): Response
    {
        $q = trim((string) $request->query('q', ''));

        $verifications = UserVerification::query()
            ->with(['user:id,name,email,avatar_url,current_verification_level,verification_tier'])
            ->whereIn('status', ['pending', 'in_review', 'flagged', 'unverified'])
            ->when($q !== '', fn ($query) => $query->whereHas('user', fn ($user) => $user->where('name', 'like', "%{$q}%")->orWhere('email', 'like', "%{$q}%")))
            ->oldest('submitted_at')
            ->paginate(20)
            ->through(fn (UserVerification $verification) => [
                'id' => $verification->id,
                'type' => $verification->verification_type ?: $verification->category?->value ?: (string) $verification->category,
                'status' => $verification->status?->value ?? (string) $verification->status,
                'submitted_at' => $verification->submitted_at?->toIso8601String(),
                'reason' => $verification->rejection_reason,
                'concern' => $verification->admin_concern,
                'user' => $verification->user ? [
                    'name' => $verification->user->name,
                    'email' => $verification->user->email,
                    'level' => $verification->user->current_verification_level ?? $verification->user->verification_tier ?? 0,
                ] : null,
            ])
            ->withQueryString();

        return Inertia::render('Operations/Verifications/Index', [
            'verifications' => $verifications,
            'filters' => ['q' => $q],
        ]);
    }
}
