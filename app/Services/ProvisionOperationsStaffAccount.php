<?php

namespace App\Services;

use App\Mail\OperationsStaffInvitationMail;
use App\Models\Role;
use App\Models\User;
use App\Support\BootstrapUserIdentity;
use App\Support\TextCasing;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;

final class ProvisionOperationsStaffAccount
{
    public function __construct(
        private AdminActivityLogger $logger,
    ) {}

    public function invite(User $inviter, string $firstName, string $lastName, string $email, ?Request $request = null): User
    {
        $req = $request ?? request();

        /** @var User $created */
        $created = DB::transaction(function () use ($inviter, $firstName, $lastName, $email): User {
            $first = TextCasing::titleWords($firstName) ?? '';
            $last = TextCasing::titleWords($lastName) ?? '';
            $email = strtolower(trim($email));
            $name = trim($first.' '.$last);
            $adminRoleId = Role::query()->where('slug', 'admin')->firstOrFail()->id;
            $identity = BootstrapUserIdentity::forEmailAndDisplayName($email, $name);
            $plainPassword = Str::password(48);

            $user = User::query()->create([
                'name' => $name,
                'first_name' => $first,
                'last_name' => $last,
                'email' => $email,
                'phone' => null,
                'account_type' => 'sponsor',
                'role_id' => $adminRoleId,
                'password' => $plainPassword,
                'username' => $identity['username'],
                'slug' => $identity['slug'],
                'uid' => $identity['uid'],
                'email_verified_at' => now(),
                'onboarding_step' => 0,
                'operations_staff_invited_at' => now(),
                'operations_staff_invited_by' => $inviter->id,
            ]);

            $setupUrl = URL::temporarySignedRoute(
                'operations.invitation.show',
                now()->addDays(7),
                ['user' => $user->id]
            );

            Mail::to($user)->send(new OperationsStaffInvitationMail($user, $setupUrl));

            return $user;
        });

        $this->logger->log(
            actor: $inviter,
            action: 'admin.operations_staff_invited',
            subjectType: User::class,
            subjectId: $created->id,
            properties: ['email' => $created->email],
            request: $req,
        );

        return $created;
    }
}
