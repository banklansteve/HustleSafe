<?php

namespace App\Observers;

use App\Models\User;
use App\Services\Geocoding\GeocodeUserAddress;

class UserObserver
{
    public function created(User $user): void
    {
        app(GeocodeUserAddress::class)($user);
    }

    public function updated(User $user): void
    {
        if ($user->wasChanged(['address_line', 'local_government', 'state'])) {
            app(GeocodeUserAddress::class)($user);
        }
    }
}
