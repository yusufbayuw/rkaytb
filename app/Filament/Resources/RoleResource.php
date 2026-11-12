<?php

namespace App\Filament\Resources;

use App\Support\StandardPermissions;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Model;
use Spatie\Permission\Models\Permission;

class RoleResource extends \BezhanSalleh\FilamentShield\Resources\Roles\RoleResource
{
    public static function getNavigationLabel(): string { return 'Role'; }
    public static function getNavigationGroup(): ?string { return 'Settings'; }
    public static function canCreate(): bool { return \App\Support\SystemAccess::allowed(); }
    public static function canEdit(Model $record): bool { return \App\Support\SystemAccess::allowed(); }
    public static function canDeleteAny(): bool { return \App\Support\SystemAccess::allowed(); }
    public static function table(\Filament\Tables\Table $table): \Filament\Tables\Table
    {
        return parent::table($table)->columns([
            \Filament\Tables\Columns\TextColumn::make('id')->label('ID')->sortable(),
            \Filament\Tables\Columns\TextColumn::make('name')->label('Nama role')->searchable()->sortable(),
            \Filament\Tables\Columns\TextColumn::make('permissions_count')->label('Jumlah permission')->counts('permissions')->badge(),
            \Filament\Tables\Columns\TextColumn::make('updated_at')->label('Diperbarui')->dateTime(),
        ]);
    }
    public static function form(\Filament\Schemas\Schema $schema): \Filament\Schemas\Schema
    {
        return $schema->components([
            \Filament\Schemas\Components\Section::make('Role')->schema([
                \Filament\Forms\Components\TextInput::make('name')->label('Nama role')->required()->maxLength(255)
                    ->unique(ignoreRecord: true, modifyRuleUsing: fn (\Illuminate\Validation\Rules\Unique $rule) => $rule->where('guard_name', config('filament.auth.guard', 'web'))),
                \Filament\Forms\Components\Hidden::make('guard_name')->default(config('filament.auth.guard', 'web'))
                    ->dehydrateStateUsing(fn () => config('filament.auth.guard', 'web')),
                static::getSelectAllFormComponent()->label('Pilih semua permission'),
            ])->columns(2)->columnSpanFull(),
            static::getShieldFormComponents(),
        ]);
    }
    public static function getEloquentQuery(): \Illuminate\Database\Eloquent\Builder
    {
        return parent::getEloquentQuery()->where('guard_name', config('filament.auth.guard', 'web'));
    }

    public static function canDelete(Model $record): bool
    {
        return $record->name !== 'superadmin' && \App\Support\SystemAccess::allowed() && parent::canDelete($record);
    }

    public static function getTabFormComponentForCustomPermissions(): Component
    {
        $options = Permission::where('guard_name', config('filament.auth.guard', 'web'))
            ->whereNotIn('name', StandardPermissions::keys())->orderBy('name')->pluck('name', 'name')->all();
        return Tab::make('Permission tambahan')->schema([
            static::getCheckboxListFormComponent('extra_permissions', $options),
        ]);
    }

    public static function getCheckBoxListComponentForResource(array $entity): Component
    {
        return parent::getCheckBoxListComponentForResource($entity)->label($entity['model']);
    }

    public static function getPages(): array
    {
        return [
            'index' => RoleResource\Pages\ManageRoles::route('/'),
            'create' => RoleResource\Pages\CreateRole::route('/create'),
            'edit' => RoleResource\Pages\EditRole::route('/{record}/edit'),
            'view' => RoleResource\Pages\ViewRole::route('/{record}'),
        ];
    }
}
