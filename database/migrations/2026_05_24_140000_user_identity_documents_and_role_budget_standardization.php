<?php

use App\Models\User;
use App\Models\UserVerification;
use App\Services\Verification\IdentityDocumentUniquenessService;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_identity_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('document_kind', 32);
            $table->char('number_hash', 64);
            $table->string('normalized_last4', 8)->nullable();
            $table->timestamps();

            $table->unique(['document_kind', 'number_hash'], 'user_identity_documents_kind_hash_unique');
            $table->index(['user_id', 'document_kind']);
        });

        $this->backfillIdentityRegistry();
        $this->standardizeRoles();
        $this->alignClientPostingLimits();
    }

    public function down(): void
    {
        Schema::dropIfExists('user_identity_documents');
    }

    private function standardizeRoles(): void
    {
        $map = [
            'admin' => ['id' => 1, 'name' => 'Staff Admin', 'description' => 'Operational platform staff with admin console access.'],
            'client' => ['id' => 2, 'name' => 'Client', 'description' => 'Posts missions, funds escrow, approves delivery.'],
            'freelancer' => ['id' => 3, 'name' => 'Freelancer', 'description' => 'Creates offers, delivers work, receives payouts.'],
            'super_admin' => ['id' => 4, 'name' => 'Super Admin', 'description' => 'Full platform control — seed sparingly.'],
        ];

        foreach ($map as $slug => $target) {
            DB::table('roles')->where('slug', $slug)->update([
                'name' => $target['name'],
                'description' => $target['description'],
                'updated_at' => now(),
            ]);
        }

        $roleIdsBySlug = DB::table('roles')->pluck('id', 'slug');

        if (isset($roleIdsBySlug['super_admin'])) {
            DB::table('users')->where('role_id', $roleIdsBySlug['super_admin'])->update(['role_id' => 4]);
        }

        if (isset($roleIdsBySlug['admin'])) {
            DB::table('users')
                ->where('role_id', $roleIdsBySlug['admin'])
                ->update(['role_id' => 1]);
        }

        if (isset($roleIdsBySlug['client'])) {
            DB::table('users')
                ->where('role_id', $roleIdsBySlug['client'])
                ->update(['role_id' => 2]);
        }

        if (isset($roleIdsBySlug['freelancer'])) {
            DB::table('users')
                ->where('role_id', $roleIdsBySlug['freelancer'])
                ->update(['role_id' => 3]);
        }

        DB::table('users')->whereNull('role_id')->where('account_type', 'admin')->update(['role_id' => 1]);
        DB::table('users')->whereNull('role_id')->whereIn('account_type', ['client', 'sponsor'])->update(['role_id' => 2]);
        DB::table('users')->whereNull('role_id')->whereIn('account_type', ['freelancer', 'hustler'])->update(['role_id' => 3]);
    }

    private function alignClientPostingLimits(): void
    {
        $limits = config('verification_engine.limits', []);
        $posting = $limits['client_posting_minor'] ?? [];
        $posting[4] = 100_000_000;
        $posting['4'] = 100_000_000;
        $limits['client_posting_minor'] = $posting;

        $row = DB::table('kyc_settings')->where('key', 'verification_limits')->first();
        if ($row !== null) {
            $stored = json_decode((string) $row->value, true);
            if (is_array($stored)) {
                $storedPosting = $stored['client_posting_minor'] ?? [];
                $storedPosting[4] = 100_000_000;
                $storedPosting['4'] = 100_000_000;
                $stored['client_posting_minor'] = $storedPosting;
                DB::table('kyc_settings')->where('key', 'verification_limits')->update([
                    'value' => json_encode($stored),
                    'updated_at' => now(),
                ]);
            }
        } else {
            DB::table('kyc_settings')->updateOrInsert(
                ['key' => 'verification_limits'],
                ['value' => json_encode($limits), 'created_at' => now(), 'updated_at' => now()],
            );
        }
    }

    private function backfillIdentityRegistry(): void
    {
        $service = app(IdentityDocumentUniquenessService::class);

        User::query()
            ->whereNotNull('nin')
            ->where('nin', '<>', '')
            ->orderBy('id')
            ->each(function (User $user) use ($service): void {
                try {
                    $service->registerForUser($user, 'nin', (string) $user->nin);
                } catch (\Throwable) {
                    // Skip duplicates during backfill — ops can reconcile manually.
                }
            });

        User::query()
            ->whereNotNull('bvn')
            ->where('bvn', '<>', '')
            ->orderBy('id')
            ->each(function (User $user) use ($service): void {
                try {
                    $service->registerForUser($user, 'bvn', (string) $user->bvn);
                } catch (\Throwable) {
                    //
                }
            });

        UserVerification::query()
            ->orderBy('id')
            ->chunkById(200, function ($rows) use ($service): void {
                foreach ($rows as $verification) {
                    $category = $verification->category?->value ?? (string) $verification->category;
                    $kind = match ($category) {
                        'nin', 'bvn' => $category,
                        'identity_address' => (string) data_get($verification->metadata, 'id_type', ''),
                        default => null,
                    };

                    if ($kind === null || $kind === '') {
                        continue;
                    }

                    $value = $category === 'bvn' && filled($verification->encrypted_identifier)
                        ? $this->decryptIdentifier($verification->encrypted_identifier)
                        : (string) data_get($verification->metadata, 'identifier_number', '');

                    if ($value === '') {
                        continue;
                    }

                    $user = User::query()->find($verification->user_id);
                    if ($user === null) {
                        continue;
                    }

                    try {
                        $service->registerForUser($user, $kind, $value);
                    } catch (\Throwable) {
                        //
                    }
                }
            });
    }

    private function decryptIdentifier(?string $encrypted): string
    {
        if ($encrypted === null || $encrypted === '') {
            return '';
        }

        try {
            return Crypt::decryptString($encrypted);
        } catch (\Throwable) {
            return '';
        }
    }
};
