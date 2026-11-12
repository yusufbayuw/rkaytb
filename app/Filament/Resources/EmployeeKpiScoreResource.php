<?php

namespace App\Filament\Resources;

use Filament\Actions;

use App\Filament\Resources\EmployeeKpiScoreResource\Pages;
use App\Filament\Resources\EmployeeKpiScoreResource\RelationManagers;
use App\Models\EmployeeKpiScore;
use Filament\Forms;
use Filament\Forms\Components\Select;
use Filament\Schemas\Schema as Form;
use Filament\Resources\Resource;
use Filament\Tables\Table;
use Filament\Tables;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class EmployeeKpiScoreResource extends Resource
{
    protected static ?string $model = EmployeeKpiScore::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-rectangle-stack';

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
            'index' => Pages\ManageEmployeeKpiScores::route('/'),
        ];
    }    
}
