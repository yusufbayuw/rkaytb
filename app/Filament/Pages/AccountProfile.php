<?php

namespace App\Filament\Pages;

use Filament\Pages\Page;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class AccountProfile extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-user-circle';
    protected static ?string $navigationLabel = 'Profil Saya';
    protected static ?string $title = 'Profil Saya';
    protected static ?string $navigationGroup = 'Akun';
    protected static string $view = 'filament.pages.account-profile';

    public string $name = '';
    public string $email = '';
    public string $currentPassword = '';
    public string $newPassword = '';
    public string $newPasswordConfirmation = '';

    public function mount(): void
    {
        $this->name = (string) auth()->user()->name;
        $this->email = (string) auth()->user()->email;
    }

    public function updateProfile(): void
    {
        $user = auth()->user();
        $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
        ]);
        $changedEmail = $user->email !== $this->email;
        $user->name = trim($this->name);
        $user->email = trim($this->email);
        if ($changedEmail && method_exists($user, 'getEmailVerifiedAt')) {
            $user->email_verified_at = null;
        }
        $user->save();
        $this->notify('success', 'Profil berhasil diperbarui.');
    }

    public function changePassword(): void
    {
        $user = auth()->user();
        $this->validate([
            'currentPassword' => ['required', 'string'],
            'newPassword' => ['required', 'string', 'min:12', 'confirmed'],
        ]);
        if (!Hash::check($this->currentPassword, $user->password)) {
            throw ValidationException::withMessages(['currentPassword' => 'Kata sandi saat ini tidak sesuai.']);
        }
        $user->password = Hash::make($this->newPassword);
        $user->save();
        $this->reset(['currentPassword', 'newPassword', 'newPasswordConfirmation']);
        $this->notify('success', 'Kata sandi telah diperbarui.');
    }
}
