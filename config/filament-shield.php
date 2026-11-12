<?php

use App\Filament\Pages;
use App\Filament\Resources;

$config = require base_path('vendor/bezhansalleh/filament-shield/config/filament-shield.php');
$config['shield_resource']['slug'] = 'roles';
$config['shield_resource']['show_model_path'] = false;
$config['shield_resource']['tabs'] = ['resources' => true, 'custom_permissions' => true, 'pages' => false, 'widgets' => false];
$config['auth_provider_model'] = App\Models\User::class;
$config['super_admin'] = ['enabled' => false, 'name' => 'superadmin', 'define_via_gate' => false, 'intercept_gate' => 'before'];
$config['panel_user']['enabled'] = false;
$config['register_role_policy'] = false;
$config['tenant_model'] = null;
$config['permissions']['case'] = 'snake';
$config['permissions']['format_custom_permission_keys'] = false;
$config['policies']['generate'] = false;
$config['policies']['methods'] = ['viewAny', 'view', 'create', 'update', 'delete'];
$config['resources']['manage'] = [];
$config['resources']['exclude'] = [
    Resources\RkaResource::class, Resources\RkaGajiResource::class, Resources\RkaConsumableResource::class,
    Resources\RkaMaintenanceResource::class, Resources\RkaOperasionalResource::class, Resources\RkaPendapatanResource::class,
    Resources\EmployeeResource::class, Resources\EmployeeKpiScoreResource::class, Resources\KpiTargetResource::class,
    Resources\PositionResource::class, Resources\SystemActivityResource::class,
];
$config['pages']['exclude'] = [Filament\Pages\Dashboard::class, Pages\AccountProfile::class,
    Pages\RkaPlatformWorkspace::class, Pages\SystemHealth::class, Pages\SystemLogs::class];
$config['widgets']['exclude'] = [Filament\Widgets\AccountWidget::class, App\Filament\Widgets\StatsOverview::class];
return $config;
