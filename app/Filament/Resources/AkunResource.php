<?php

namespace App\Filament\Resources;

use Filament\Actions;

use App\Filament\Resources\AkunResource\Pages;
use App\Filament\Resources\AkunResource\RelationManagers;
use App\Models\Akun;
use App\Models\Setting;
use Filament\Forms;
use Filament\Schemas\Schema as Form;
use Filament\Resources\Resource;
use Filament\Tables\Table;
use Filament\Tables;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class AkunResource extends Resource
{
    protected static ?string $model = Akun::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-document-text';

    protected static string|\UnitEnum|null $navigationGroup = 'Keuangan';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('nama')
                    ->required()
                    ->maxLength(255),
                Forms\Components\TextInput::make('keterangan')
                    ->maxLength(255),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns((Setting::flag(2)) ? [] : [
                Tables\Columns\TextColumn::make('nama'),
                Tables\Columns\TextColumn::make('keterangan'),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime(),
                Tables\Columns\TextColumn::make('updated_at')
                    ->dateTime(),
            ])
            ->filters([
                //
            ])
            ->recordActions((Setting::flag(2)) ? [] : [
                Actions\EditAction::make(),
                Actions\DeleteAction::make(),
            ])
            ->toolbarActions((Setting::flag(2)) ? [] : [
                Actions\DeleteBulkAction::make(),
            ]);
    }
    
    public static function getPages(): array
    {
        return [
            'index' => Pages\ManageAkuns::route('/'),
        ];
    }    
}
