<?php

namespace App\Filament\Resources\RoleResource\Pages;

use App\Services\AccessManagement;
use Illuminate\Validation\ValidationException;

class EditRole extends \BezhanSalleh\FilamentShield\Resources\Roles\Pages\EditRole
{
    use \App\Support\RequiresSystemAccess;
    protected static string $resource = \App\Filament\Resources\RoleResource::class;
    protected ?bool $hasDatabaseTransactions = true;
    protected array $previousPermissions = [];

    protected function mutateFormDataBeforeSave(array $data): array
    {
        \App\Support\SystemAccess::authorize();
        if ($this->record->name === 'superadmin' && ($data['name'] ?? '') !== 'superadmin') {
            throw ValidationException::withMessages(['name' => 'Nama role superadmin tidak dapat diubah.']);
        }
        $this->previousPermissions = $this->record->permissions()->pluck('name')->all();
        $data['guard_name'] = $this->record->guard_name;
        $result = parent::mutateFormDataBeforeSave($data);
        AccessManagement::validatePermissions($this->permissions->all());
        return $result;
    }

    protected function afterSave(): void
    {
        parent::afterSave();
        AccessManagement::auditAssignment($this->record, $this->previousPermissions, $this->permissions->all(), 'permissions');
    }
}
