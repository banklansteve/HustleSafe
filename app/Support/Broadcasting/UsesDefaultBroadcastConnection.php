<?php

namespace App\Support\Broadcasting;

trait UsesDefaultBroadcastConnection
{
    public function broadcastConnection(): ?string
    {
        $connection = (string) config('broadcasting.default', 'null');

        if (in_array($connection, ['null', '', 'log'], true)) {
            return null;
        }

        return $connection;
    }
}
