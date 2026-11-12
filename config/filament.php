<?php

return [
    // Retain the existing environment contract; panel behavior lives in AdminPanelProvider.
    'path' => env('FILAMENT_PATH', 'admin'),
    'domain' => env('FILAMENT_DOMAIN'),
    'auth' => ['guard' => env('FILAMENT_AUTH_GUARD', 'web')],
    'default_filesystem_disk' => env('FILAMENT_FILESYSTEM_DISK', env('FILAMENT_FILESYSTEM_DRIVER', 'local')),
];
