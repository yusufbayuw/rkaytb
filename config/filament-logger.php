<?php

$config = require base_path('vendor/mradder/filament-logger/config/filament-logger.php');
$config['activity_resource'] = App\Filament\Resources\SystemActivityResource::class;
$config['scoped_to_tenant'] = false;
$config['resources']['navigation_group'] = 'Sistem';
$config['resources']['logger'] = App\Services\AuditResourceLogger::class;
$config['resources']['ignore'] = ['updated_at', 'remember_token', 'password', 'is_editmode'];
$config['models']['enabled'] = false;
$config['notifications']['enabled'] = false;
$config['alerts']['enabled'] = false;
$config['dashboard']['enabled'] = false;
$config['access']['guards'] = [config('filament.auth.guard', 'web')];
$config['exports']['queue']['enabled'] = false;
$config['exports']['queue']['routes'] = false;
$config['sensitive_keys'] = array_merge($config['sensitive_keys'], ['currentPassword', 'newPassword', 'app_authentication_secret', 'app_authentication_recovery_codes']);
return $config;
