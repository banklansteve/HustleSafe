<?php

namespace App\Services\Verification;

use App\Enums\UserVerificationCategory;
use App\Models\KycReviewCase;
use App\Models\User;
use App\Models\UserVerification;
use Illuminate\Database\Eloquent\Builder;

/**
 * Defines which verification submissions staff admins may review vs Super Admin only.
 */
final class VerificationStaffReviewPolicy
{
    /**
     * @return list<string>
     */
    public function clientSuperAdminOnlyTypes(): array
    {
        return array_values(array_map(
            'strval',
            config('verification_engine.staff_review.client_super_admin_only_types', ['bvn']),
        ));
    }

    /**
     * @return list<string>
     */
    public function freelancerSuperAdminOnlyTypes(): array
    {
        return array_values(array_map(
            'strval',
            config('verification_engine.staff_review.freelancer_super_admin_only_types', ['live_presence']),
        ));
    }

    public function typeKey(UserVerification $verification): string
    {
        $category = $verification->category instanceof UserVerificationCategory
            ? $verification->category->value
            : (string) ($verification->category ?? '');

        if ($category !== '') {
            return $this->normalizeTypeKey($category);
        }

        $type = trim((string) ($verification->verification_type ?: ''));

        return $this->normalizeTypeKey($type !== '' ? $type : 'unknown');
    }

    public function isFreelancerAccount(User $user): bool
    {
        $user->loadMissing('role:id,slug');
        $slug = $user->role?->slug ?? $user->account_type;

        return in_array($slug, ['freelancer', 'seller', 'provider'], true);
    }

    public function isClientAccount(User $user): bool
    {
        $user->loadMissing('role:id,slug');

        return ($user->role?->slug ?? $user->account_type) === 'client';
    }

    public function requiresSuperAdminReview(UserVerification $verification): bool
    {
        $verification->loadMissing(['user.role:id,slug']);
        $user = $verification->user;
        if ($user === null) {
            return false;
        }

        $typeKey = $this->typeKey($verification);

        if ($this->isClientAccount($user)) {
            return in_array($typeKey, $this->clientSuperAdminOnlyTypes(), true);
        }

        if ($this->isFreelancerAccount($user)) {
            return in_array($typeKey, $this->freelancerSuperAdminOnlyTypes(), true);
        }

        return in_array($typeKey, array_merge(
            $this->clientSuperAdminOnlyTypes(),
            $this->freelancerSuperAdminOnlyTypes(),
        ), true);
    }

    public function staffCanReview(UserVerification $verification, User $staff): bool
    {
        if ($staff->role?->slug === 'super_admin') {
            return true;
        }

        if ($staff->role?->slug !== 'admin') {
            return false;
        }

        return ! $this->requiresSuperAdminReview($verification);
    }

    public function staffCanReviewKycCase(KycReviewCase $case, User $staff): bool
    {
        if ($staff->role?->slug === 'super_admin') {
            return true;
        }

        $case->loadMissing(['user.role', 'verification']);

        if ($case->verification !== null) {
            return $this->staffCanReview($case->verification, $staff);
        }

        if ($staff->role?->slug !== 'admin') {
            return false;
        }

        $typeKey = $this->normalizeTypeKey((string) $case->verification_type);
        $user = $case->user;

        if ($user !== null && $this->isClientAccount($user)) {
            return ! in_array($typeKey, $this->clientSuperAdminOnlyTypes(), true);
        }

        if ($user !== null && $this->isFreelancerAccount($user)) {
            return ! in_array($typeKey, $this->freelancerSuperAdminOnlyTypes(), true);
        }

        return ! in_array($typeKey, array_merge(
            $this->clientSuperAdminOnlyTypes(),
            $this->freelancerSuperAdminOnlyTypes(),
        ), true);
    }

    /**
     * @param  Builder<KycReviewCase>  $query
     * @return Builder<KycReviewCase>
     */
    public function applyKycCaseStaffReviewableOnly(Builder $query): Builder
    {
        $clientOnly = $this->clientSuperAdminOnlyTypes();
        $freelancerOnly = $this->freelancerSuperAdminOnlyTypes();

        return $query->where(function (Builder $eligible) use ($clientOnly, $freelancerOnly): void {
            $eligible->where(function (Builder $client) use ($clientOnly): void {
                $client->whereHas('user', function (Builder $user): void {
                    $user->where(function (Builder $bucket): void {
                        $bucket->whereHas('role', fn (Builder $role) => $role->where('slug', 'client'))
                            ->orWhere('account_type', 'client');
                    });
                });

                if ($clientOnly !== []) {
                    $client->whereNotIn('verification_type', $clientOnly);
                }
            })->orWhere(function (Builder $freelancer) use ($freelancerOnly): void {
                $freelancer->whereHas('user', function (Builder $user): void {
                    $user->where(function (Builder $bucket): void {
                        $bucket->whereHas('role', fn (Builder $role) => $role->whereIn('slug', ['freelancer', 'seller', 'provider']))
                            ->orWhereIn('account_type', ['freelancer', 'seller', 'provider']);
                    });
                });

                if ($freelancerOnly !== []) {
                    $freelancer->whereNotIn('verification_type', $freelancerOnly);
                }
            })->orWhereHas('user', function (Builder $user): void {
                $user->where(function (Builder $bucket): void {
                    $bucket->whereDoesntHave('role', fn (Builder $role) => $role->whereIn('slug', ['client', 'freelancer', 'seller', 'provider']))
                        ->where(function (Builder $accountType): void {
                            $accountType->whereNull('account_type')
                                ->orWhereNotIn('account_type', ['client', 'freelancer', 'seller', 'provider']);
                        });
                });
            });
        });
    }

    public function assertStaffCanReviewKycCase(KycReviewCase $case, User $staff): void
    {
        abort_unless(
            $this->staffCanReviewKycCase($case, $staff),
            403,
            __('This verification is reviewed by Super Admin only.'),
        );
    }

    /**
     * Pending submissions staff admins should see in their operations queue.
     *
     * @param  Builder<UserVerification>  $query
     * @return Builder<UserVerification>
     */
    public function staffQueueQuery(Builder $query, ?User $staff = null): Builder
    {
        $query->whereNotNull('submitted_at');

        $this->applyStaffReviewableOnly($query);

        if ($staff !== null) {
            $this->applyStaffAssignmentVisibility($query, $staff);
        }

        return $query;
    }

    /**
     * Staff admins see unassigned work, their own assignments, and staff-reviewable
     * cases that were incorrectly auto-assigned to Super Admin.
     *
     * @param  Builder<UserVerification>  $query
     */
    private function applyStaffAssignmentVisibility(Builder $query, User $staff): void
    {
        $staff->loadMissing('role:id,slug');

        $query->where(function (Builder $scope) use ($staff): void {
            $scope->whereNull('assigned_staff_id')
                ->orWhere('assigned_staff_id', $staff->id);

            if ($staff->role?->slug === 'admin') {
                $scope->orWhereHas('assignedStaff.role', fn (Builder $role) => $role->where('slug', 'super_admin'));
            }
        });
    }

    /**
     * @param  Builder<UserVerification>  $query
     * @return Builder<UserVerification>
     */
    public function applyStaffReviewableOnly(Builder $query): Builder
    {
        $clientOnly = $this->categoryValues($this->clientSuperAdminOnlyTypes());
        $freelancerOnly = $this->categoryValues($this->freelancerSuperAdminOnlyTypes());

        return $query->where(function (Builder $eligible) use ($clientOnly, $freelancerOnly): void {
            $eligible->where(function (Builder $client) use ($clientOnly): void {
                $client->whereHas('user', function (Builder $user): void {
                    $user->where(function (Builder $bucket): void {
                        $bucket->whereHas('role', fn (Builder $role) => $role->where('slug', 'client'))
                            ->orWhere('account_type', 'client');
                    });
                });

                if ($clientOnly !== []) {
                    $client->whereNotIn('category', $clientOnly);
                }
            })->orWhere(function (Builder $freelancer) use ($freelancerOnly): void {
                $freelancer->whereHas('user', function (Builder $user): void {
                    $user->where(function (Builder $bucket): void {
                        $bucket->whereHas('role', fn (Builder $role) => $role->whereIn('slug', ['freelancer', 'seller', 'provider']))
                            ->orWhereIn('account_type', ['freelancer', 'seller', 'provider']);
                    });
                });

                if ($freelancerOnly !== []) {
                    $freelancer->whereNotIn('category', $freelancerOnly);
                }
            })->orWhereHas('user', function (Builder $user): void {
                $user->where(function (Builder $bucket): void {
                    $bucket->whereDoesntHave('role', fn (Builder $role) => $role->whereIn('slug', ['client', 'freelancer', 'seller', 'provider']))
                        ->where(function (Builder $accountType): void {
                            $accountType->whereNull('account_type')
                                ->orWhereNotIn('account_type', ['client', 'freelancer', 'seller', 'provider']);
                        });
                });
            });
        });
    }

    /**
     * @param  list<string>  $types
     * @return list<string>
     */
    private function categoryValues(array $types): array
    {
        return array_values(array_filter(array_map(function (string $type): string {
            return UserVerificationCategory::tryFrom($type)?->value
                ?? $this->normalizeTypeKey($type);
        }, $types)));
    }

    private function normalizeTypeKey(string $key): string
    {
        $key = strtolower(trim($key));

        if (in_array($key, [
            'identity',
            'address',
            'identity_address',
            'identity-address',
            'identity_and_address',
            'identity-and-address',
        ], true)) {
            return UserVerificationCategory::IdentityAddress->value;
        }

        return $key;
    }
}
