<?php

namespace App\Filament\Resources;

use Filament\Actions;

use Filament\Forms;
use Filament\Tables;
use Filament\Schemas\Schema as Form;
use Filament\Tables\Table;
use Filament\Resources\Resource;
use Filament\Tables\Columns\TextColumn;
use Filament\Forms\Components\TextInput;
use Spatie\Permission\Models\Permission;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use App\Filament\Resources\PermissionResource\Pages;
use App\Filament\Resources\PermissionResource\RelationManagers;

class PermissionResource extends Resource
{
    protected static ?string $model = Permission::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-key';

    protected static string|\UnitEnum|null $navigationGroup = 'Settings';
    public static function canCreate(): bool { return \App\Support\SystemAccess::allowed(); }
    public static function canEdit(\Illuminate\Database\Eloquent\Model $record): bool { return \App\Support\SystemAccess::allowed(); }
    public static function canDelete(\Illuminate\Database\Eloquent\Model $record): bool { return \App\Support\SystemAccess::allowed(); }
    public static function canDeleteAny(): bool { return \App\Support\SystemAccess::allowed(); }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->where('guard_name', config('filament.auth.guard', 'web'));
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                TextInput::make('name')->unique(ignoreRecord:true)->required(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')->sortable(),
                TextColumn::make('name')->sortable()->searchable(),
                TextColumn::make('created_at')->dateTime('d-M-Y')->sortable(),
            ])
            ->filters([
                //
            ])
            ->recordActions([
                Actions\EditAction::make(),
                Actions\DeleteAction::make(),
            ])
            ->toolbarActions([
                Actions\DeleteBulkAction::make(),
            ]);
    }
    
    public static function getPages(): array
    {
        return [
            'index' => Pages\ManagePermissions::route('/'),
        ];
    }    
}
