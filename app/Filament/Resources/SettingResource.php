<?php

namespace App\Filament\Resources;

use Filament\Actions;

use App\Filament\Resources\SettingResource\Pages;
use App\Filament\Resources\SettingResource\RelationManagers;
use App\Models\Setting;
use Filament\Forms;
use Filament\Schemas\Schema as Form;
use Filament\Resources\Resource;
use Filament\Tables\Table;
use Filament\Tables;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class SettingResource extends Resource
{
    protected static ?string $model = Setting::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-s-cog';

    protected static string|\UnitEnum|null $navigationGroup = 'Settings';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('nama')
                    ->label('Nama Pengaturan')
                    ->required()
                    ->maxLength(255),
                Forms\Components\Toggle::make('valbool')
                    ->label('Status')
                    ->required(),
                Forms\Components\TextInput::make('value')
                    ->maxLength(255),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('id'),
                Tables\Columns\TextColumn::make('nama'),
                Tables\Columns\IconColumn::make('valbool')
                    ->boolean()->label('Status')->action(function ($record, $column) {
                        $name = $column->getName();
                        $record->update([
                            $name => !$record->$name
                        ]);
                    }),
                Tables\Columns\TextInputColumn::make('value')->label('Keterangan')->toggleable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()->hidden(),
                Tables\Columns\TextColumn::make('updated_at')
                    ->dateTime()->hidden(),
            ])
            ->filters([
                //
            ])
            ->recordActions([
                Actions\EditAction::make()->hidden(),
                Actions\DeleteAction::make()->hidden(),
            ])
            ->toolbarActions([
                Actions\DeleteBulkAction::make()->hidden(),
            ]);
    }
    
    public static function getPages(): array
    {
        return [
            'index' => Pages\ManageSettings::route('/'),
        ];
    }    
}
