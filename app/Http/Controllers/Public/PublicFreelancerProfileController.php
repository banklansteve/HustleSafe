<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\User;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class PublicFreelancerProfileController extends Controller
{
    public function __invoke(string $slug): Response
    {
        $user = User::query()
            ->where('slug', $slug)
            ->whereHas('role', fn ($q) => $q->where('slug', 'freelancer'))
            ->with([
                'stateModel',
                'localGovernmentModel',
                'freelancerBusinessProfile',
                'freelancerCredentials' => fn ($q) => $q->where('is_public', true),
            ])
            ->first();

        if ($user === null) {
            throw new NotFoundHttpException;
        }

        return Inertia::render('Public/FreelancerProfile', [
            'profile' => [
                'name' => $user->name,
                'headline' => $user->headline,
                'bio' => $user->bio,
                'profession' => $user->profession,
                'years_experience' => $user->years_experience,
                'hourly_rate_min' => $user->hourly_rate_min,
                'hourly_rate_max' => $user->hourly_rate_max,
                'verification_tier' => $user->verification_tier,
                'trust_score' => $user->trust_score,
                'avg_rating' => $user->avg_rating_as_freelancer,
                'rating_count' => $user->ratings_count_as_freelancer,
                'state' => $user->stateModel?->name,
                'local_government' => $user->localGovernmentModel?->name,
                'avatar_url' => $user->avatar_url,
                'cac' => $user->freelancerBusinessProfile ? [
                    'registration_number' => $user->freelancerBusinessProfile->cac_registration_number,
                    'status' => $user->freelancerBusinessProfile->cac_verification_status,
                    'verified_at' => $user->freelancerBusinessProfile->cac_verified_at?->toIso8601String(),
                ] : null,
                'credentials' => $user->freelancerCredentials->map(fn ($c) => [
                    'type' => $c->credential_type,
                    'title' => $c->title,
                    'issuing_authority' => $c->issuing_authority,
                    'reference_number' => $c->reference_number,
                    'issued_on' => $c->issued_on?->toDateString(),
                    'expires_on' => $c->expires_on?->toDateString(),
                    'coverage_summary' => $c->coverage_summary,
                    'is_verified' => $c->is_verified,
                ]),
            ],
        ]);
    }
}
