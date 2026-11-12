<?php

namespace App\Filament\Pages;

use Filament\Pages\Page;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class AccountProfile extends Page
{
    protected static ?string $slug = 'profile'; // Preserve the former profile URL for existing bookmarks.
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-user-circle';
    protected static ?string $navigationLabel = 'Profil Saya';
    protected static ?string $title = 'Profil Saya';
    protected static string|\UnitEnum|null $navigationGroup = 'Akun';
    protected string $view = 'filament.pages.account-profile';

    public string $name = '';
    public string $email = '';
    public string $currentPassword = '';
    public string $newPassword = '';
    public string $newPassword_confirmation = '';

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
        if ($changedEmail) {
            $user->email_verified_at = null;
        }
        $user->save();
        \Filament\Notifications\Notification::make()->success()->title('Profil berhasil diperbarui.')->send();
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
        $this->reset(['currentPassword', 'newPassword', 'newPassword_confirmation']);
        \Filament\Notifications\Notification::make()->success()->title('Kata sandi telah diperbarui.')->send();
    }
}
