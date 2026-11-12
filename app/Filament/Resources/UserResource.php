<?php

namespace App\Filament\Resources;

use Filament\Actions;

use Filament\Forms;
use App\Models\Unit;
use App\Models\User;
use Filament\Tables;
use Filament\Schemas\Schema as Form;
use Filament\Tables\Table;
use Filament\Resources\Resource;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Hash;
use Filament\Tables\Columns\TextColumn;
use Illuminate\Database\Eloquent\Builder;
use App\Filament\Resources\UserResource\Pages;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use App\Filament\Resources\UserResource\RelationManagers;
use Filament\Forms\Components\CheckboxList;

class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static ?string $modelLabel = 'Pengguna';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-user-group';

    protected static ?string $slug = 'pengguna';

    protected static ?string $navigationLabel = 'Pengguna';

    protected static string|\UnitEnum|null $navigationGroup = 'Settings';

    public static function getEloquentQuery(): Builder
    {
        if (auth()->user()->hasRole('superadmin')) {
            return parent::getEloquentQuery();
        } else {
            return parent::getEloquentQuery()->where('unit_id', auth()->user()->unit_id);
        }
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('name')
                    ->required()
                    ->maxLength(255),
                Forms\Components\TextInput::make('email')
                    ->email()
                    ->required()
                    ->unique(ignoreRecord: true)
                    ->maxLength(255),
                Forms\Components\Select::make('unit_id')
                    ->label('Unit')
                    ->nullable()
                    ->options(
                        Unit::all()->pluck('nama', 'id')
                    )->default(fn () => (auth()->user()->hasRole('superadmin') ? null : (auth()->user()->unit_id)))
                    ->disabled(!(auth()->user()->hasRole('superadmin')))->dehydrated()
                    ->dehydrateStateUsing(fn ($state) => auth()->user()->hasRole('superadmin') ? $state : auth()->user()->unit_id),
                Forms\Components\TextInput::make('password')
                    ->password()
                    ->required(fn (string $operation) => $operation === 'create')
                    ->minLength(12)
                    ->maxLength(255)
                    ->dehydrateStateUsing(static fn (null|string $state): null|string => filled($state) ? Hash::make($state) : null,)
                    ->dehydrated(static fn (null|string $state): bool => filled($state)),
                CheckboxList::make('roles')
                    ->relationship('roles', 'name', modifyQueryUsing: fn (Builder $query) => $query->where('guard_name', config('filament.auth.guard', 'web')))
                    ->saveRelationshipsUsing(fn (User $record, $state) => \App\Services\AccessManagement::syncRoles($record, $state ?? []))
                    ->columns(2)
                    ->hidden(!(auth()->user()->hasRole('superadmin'))),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name'),
                TextColumn::make('email'),
                TextColumn::make('unit_id')->formatStateUsing(fn ($state) => Unit::find($state)?->nama ?? '—'),
                TextColumn::make('created_at')
                    ->dateTime(),
                TextColumn::make('updated_at')
                    ->dateTime(),
            ])
            ->filters([
                //
            ])
            ->recordActions([
                Actions\EditAction::make(),
                Actions\DeleteAction::make()->using(fn (User $record) => \App\Services\AccessManagement::deleteUser($record)),
            ])
            ->toolbarActions([
                Actions\DeleteBulkAction::make()->using(function ($records) {
                    foreach ($records as $record) \App\Services\AccessManagement::deleteUser($record);
                }),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ManageUsers::route('/'),
        ];
    }
}
