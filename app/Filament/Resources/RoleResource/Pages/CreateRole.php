<?php

namespace App\Filament\Resources\RoleResource\Pages;

use App\Services\AccessManagement;

class CreateRole extends \BezhanSalleh\FilamentShield\Resources\Roles\Pages\CreateRole
{
    use \App\Support\RequiresSystemAccess;
    protected static string $resource = \App\Filament\Resources\RoleResource::class;
    protected ?bool $hasDatabaseTransactions = true;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        \App\Support\SystemAccess::authorize();
        $data['guard_name'] = config('filament.auth.guard', 'web');
        $result = parent::mutateFormDataBeforeCreate($data);
        AccessManagement::validatePermissions($this->permissions->all());
        return $result;
    }

    protected function afterCreate(): void
    {
        parent::afterCreate();
        AccessManagement::auditAssignment($this->record, [], $this->permissions->all(), 'permissions');
    }
}
