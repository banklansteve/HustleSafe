<?php

use App\Providers\AppServiceProvider;
use App\Providers\DatabaseSafetyServiceProvider;

return [
    DatabaseSafetyServiceProvider::class,
    AppServiceProvider::class,
];
