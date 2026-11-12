<?php

namespace App\Filament\Resources;

use Filament\Actions;

use Filament\Forms;
use Filament\Tables;
use App\Models\Position;
use Filament\Schemas\Schema as Form;
use Filament\Tables\Table;
use Filament\Resources\Resource;
use Filament\Forms\Components\TextInput;
use Illuminate\Database\Eloquent\Builder;
use App\Filament\Resources\PositionResource\Pages;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use App\Filament\Resources\PositionResource\RelationManagers;
use Closure;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Select;

class PositionResource extends Resource
{
    protected static ?string $model = Position::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-rectangle-stack';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                TextInput::make('nama')
                    ->required()
                    ->maxLength(255),
                Select::make('parent_id')->options(
                    function (\Filament\Schemas\Components\Utilities\Get $get) {
                        return Position::where('id', '!=', ($get('id')))->tree()->depthFirst()->pluck('nama', 'id');
                    }
                ),
                CheckboxList::make('kpiTargets')->relationship('kpiTargets', 'nama'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('parent_id'),
                Tables\Columns\TextColumn::make('nama'),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime(),
                Tables\Columns\TextColumn::make('updated_at')
                    ->dateTime(),
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
            'index' => Pages\ManagePositions::route('/'),
        ];
    }    
}
