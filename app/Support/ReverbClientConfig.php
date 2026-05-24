<?php

namespace App\Support;

/**
 * @deprecated Use BroadcastClientConfig — kept for backward compatibility.
 */
class ReverbClientConfig
{
    /**
     * @return array<string, mixed>
     */
    public static function forRequest(): array
    {
        return BroadcastClientConfig::forRequest();
    }
}
