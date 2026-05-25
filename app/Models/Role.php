<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Role extends Model
{
    public const STAFF_ADMIN_ID = 1;

    public const CLIENT_ID = 2;

    public const FREELANCER_ID = 3;

    public const SUPER_ADMIN_ID = 4;

    public const SLUG_STAFF_ADMIN = 'admin';

    public const SLUG_CLIENT = 'client';

    public const SLUG_FREELANCER = 'freelancer';

    public const SLUG_SUPER_ADMIN = 'super_admin';

    /**
     * @return array<int, string>
     */
    public static function standardLabels(): array
    {
        return [
            self::STAFF_ADMIN_ID => 'Staff Admin',
            self::CLIENT_ID => 'Client',
            self::FREELANCER_ID => 'Freelancer',
            self::SUPER_ADMIN_ID => 'Super Admin',
        ];
    }

    public function standardLabel(): string
    {
        return self::standardLabels()[(int) $this->id] ?? (string) $this->name;
    }

    /**
     * @return HasMany<User, $this>
     */
    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }
}
