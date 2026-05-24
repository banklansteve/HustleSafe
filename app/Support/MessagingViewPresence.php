<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;

/**
 * Tracks when a user is actively viewing a messaging surface so inbound messages
 * can be marked read and notifications suppressed.
 */
class MessagingViewPresence
{
    public const SCOPE_QUEST_THREAD = 'quest_thread';

    public const SCOPE_ADMIN_DM = 'admin_dm';

    public const SCOPE_TEAM_CHAT_ROOM = 'team_chat_room';

    public const SCOPE_CUSTOMER_SUPPORT = 'customer_support';

    public static function touch(string $scope, int $resourceId, int $userId, int $seconds = 90): void
    {
        Cache::put(self::key($scope, $resourceId, $userId), 1, now()->addSeconds($seconds));
    }

    public static function isViewing(string $scope, int $resourceId, int $userId): bool
    {
        return (bool) Cache::get(self::key($scope, $resourceId, $userId));
    }

    private static function key(string $scope, int $resourceId, int $userId): string
    {
        return "messaging_view:{$scope}:{$resourceId}:{$userId}";
    }
}
