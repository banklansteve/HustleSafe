<?php

return [
    'performance_threshold' => (float) env('HR_PERFORMANCE_THRESHOLD', 55),
    'suspicious_flags_30d_threshold' => (int) env('HR_SUSPICIOUS_FLAGS_30D_THRESHOLD', 2),
];
