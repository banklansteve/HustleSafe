<?php

require __DIR__.'/vendor/autoload.php';
$app = require __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
app(\App\Http\Controllers\Operations\OperationsConversationMonitoringController::class);
app(\App\Http\Controllers\Admin\AdminConversationMonitoringController::class);
echo "OK\n";
