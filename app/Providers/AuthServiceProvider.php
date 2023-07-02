<?php

namespace App\Providers;

// use Illuminate\Support\Facades\Gate;

use App\Models\Akun;
use App\Models\Setting;
use App\Models\Unit;
use App\Models\User;
use App\Policies\AkunPolicy;
use App\Policies\RolePolicy;
use App\Policies\UserPolicy;
use App\Policies\PermissionPolicy;
use App\Policies\SettingPolicy;
use App\Policies\UnitPolicy;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * The model to policy mappings for the application.
     *
     * @var array<class-string, class-string>
     */
    protected $policies = [
        Permission::class => PermissionPolicy::class,
        Role::class => RolePolicy::class,
        User::class => UserPolicy::class,
        Unit::class => UnitPolicy::class,
        Akun::class => AkunPolicy::class,
        Setting::class => SettingPolicy::class,
    ];

    /**
     * Register any authentication / authorization services.
     */
    public function boot(): void
    {
        //
    }
}
