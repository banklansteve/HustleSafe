<?php

return [

    /**
     * A user is considered "online" if last_active_at is within this many minutes.
     */
    'online_within_minutes' => (int) env('PRESENCE_ONLINE_WITHIN_MINUTES', 5),

];
