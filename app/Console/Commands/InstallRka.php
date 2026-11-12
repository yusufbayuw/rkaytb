<?php

namespace App\Console\Commands;

use App\Models\Setting;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use App\Models\Role;

class InstallRka extends Command
{
    protected $signature = 'rka:install {--name= : Nama administrator} {--email= : Email administrator}';

    protected $description = 'Siapkan pengaturan teknis dan akun superadmin tanpa data organisasi contoh';

    public function handle(): int
    {
        Setting::initializeDefaults();
        \App\Support\StandardPermissions::sync();
        $name = $this->option('name') ?: $this->ask('Nama administrator');
        $email = $this->option('email') ?: $this->ask('Email administrator');
        $validator = Validator::make(compact('name', 'email'), [
            'name' => ['required', 'string', 'max:255'], 'email' => ['required', 'email', 'max:255'],
        ]);
        if ($validator->fails()) {
            $this->error($validator->errors()->first());
            return self::FAILURE;
        }
        $existing = User::where('email', $email)->first();
        if ($existing) {
            if ($existing->hasRole('superadmin')) {
                $this->info('Administrator dan pengaturan sudah tersedia; akun tidak diubah.');
                return self::SUCCESS;
            }
            $this->error('Email sudah digunakan. Perintah ini tidak menaikkan hak akses akun existing.');
            return self::FAILURE;
        }
        $password = $this->secret('Kata sandi administrator (minimal 12 karakter)');
        $confirmation = $this->secret('Ulangi kata sandi');
        if (!is_string($password) || mb_strlen($password) < 12 || $password !== $confirmation) {
            $this->error('Kata sandi harus cocok dan minimal 12 karakter.');
            return self::FAILURE;
        }
        DB::transaction(function () use ($name, $email, $password) {
            $role = Role::findOrCreate('superadmin', config('filament.auth.guard', 'web'));
            $user = User::create(['name' => trim($name), 'email' => trim($email), 'password' => Hash::make($password)]);
            $user->assignRole($role);
        });
        $this->info('Administrator siap. Masuk melalui /'.trim(config('filament.path', 'admin'), '/').'/login.');
        return self::SUCCESS;
    }
}
