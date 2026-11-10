<x-filament::page>
    <div class="grid lg:grid-cols-2 gap-6">
        <div class="rounded-xl bg-white dark:bg-gray-800 shadow p-5">
            <h2 class="text-lg font-semibold mb-4">Identitas Akun</h2>
            <form wire:submit.prevent="updateProfile" class="space-y-3">
                <div><label for="profile-name" class="block text-sm mb-1">Nama</label>
                    <input id="profile-name" wire:model.defer="name" class="border w-full rounded-lg p-2 dark:bg-gray-900" autocomplete="name" /></div>
                <div><label for="profile-email" class="block text-sm mb-1">Email</label>
                    <input id="profile-email" wire:model.defer="email" type="email" class="border w-full rounded-lg p-2 dark:bg-gray-900" autocomplete="email" /></div>
                <button class="px-4 py-2 rounded-lg bg-primary-600 text-white" type="submit">Simpan Profil</button>
            </form>
        </div>
        <div class="rounded-xl bg-white dark:bg-gray-800 shadow p-5">
            <h2 class="text-lg font-semibold mb-4">Ganti Kata Sandi</h2>
            <form wire:submit.prevent="changePassword" class="space-y-3">
                <div><label for="current-password" class="block text-sm mb-1">Kata sandi saat ini</label>
                    <input id="current-password" wire:model.defer="currentPassword" type="password" class="border w-full rounded-lg p-2 dark:bg-gray-900" autocomplete="current-password" /></div>
                <div><label for="new-password" class="block text-sm mb-1">Kata sandi baru (minimal 12 karakter)</label>
                    <input id="new-password" wire:model.defer="newPassword" type="password" class="border w-full rounded-lg p-2 dark:bg-gray-900" autocomplete="new-password" /></div>
                <div><label for="new-password-confirmation" class="block text-sm mb-1">Ulangi kata sandi baru</label>
                    <input id="new-password-confirmation" wire:model.defer="newPasswordConfirmation" type="password" class="border w-full rounded-lg p-2 dark:bg-gray-900" autocomplete="new-password" /></div>
                <button class="px-4 py-2 rounded-lg bg-primary-600 text-white" type="submit">Ubah Kata Sandi</button>
            </form>
        </div>
    </div>
    @if($errors->any())
        <div class="border border-red-400 rounded-lg p-4">
            <ul class="list-disc ml-5 text-sm">
                @foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach
            </ul>
        </div>
    @endif
</x-filament::page>
