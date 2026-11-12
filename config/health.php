<?php

$config = require base_path('vendor/spatie/laravel-health/config/health.php');
$config['result_stores'][Spatie\Health\ResultStores\EloquentHealthResultStore::class]['keep_history_for_days'] = 7;
$config['notifications']['enabled'] = false;
$config['oh_dear_endpoint']['enabled'] = false;
return $config;
