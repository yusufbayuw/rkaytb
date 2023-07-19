<?php

namespace App\Filament\Resources;

use App\Filament\Resources\EmployeeKpiScoreResource\Pages;
use App\Filament\Resources\EmployeeKpiScoreResource\RelationManagers;
use App\Models\EmployeeKpiScore;
use Filament\Forms;
use Filament\Forms\Components\Select;
use Filament\Resources\Form;
use Filament\Resources\Resource;
use Filament\Resources\Table;
use Filament\Tables;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class EmployeeKpiScoreResource extends Resource
{
    protected static ?string $model = EmployeeKpiScore::class;

    protected static ?string $navigationIcon = 'heroicon-o-collection';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Select::make('employee_id')->relationship('employee', 'nama')
                    ->required(),
                Forms\Components\TextInput::make('kpi_target_id')
                    ->required(),
                Forms\Components\TextInput::make('score')
                    ->required(),
                Forms\Components\TextInput::make('max_point')
                    ->required(),
                Forms\Components\TextInput::make('subtotal')
                    ->required(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('employee.nama'),
                Tables\Columns\TextColumn::make('kpiTarget.nama'),
                Tables\Columns\TextColumn::make('score'),
                Tables\Columns\TextColumn::make('max_point'),
                Tables\Columns\TextColumn::make('subtotal'),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime(),
                Tables\Columns\TextColumn::make('updated_at')
                    ->dateTime(),
            ])
            ->filters([
                //
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\DeleteBulkAction::make(),
            ]);
    }
    
    public static function getPages(): array
    {
        return [
            'index' => Pages\ManageEmployeeKpiScores::route('/'),
        ];
    }    
}
