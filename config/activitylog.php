<?php

$config = require base_path('vendor/spatie/laravel-activitylog/config/activitylog.php');
$config['buffer']['enabled'] = false;
$config['default_auth_driver'] = config('filament.auth.guard', 'web');
return $config;
