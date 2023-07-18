<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PegawaiJabatanKpiResource\Pages;
use App\Filament\Resources\PegawaiJabatanKpiResource\RelationManagers;
use App\Models\PegawaiJabatanKpi;
use Filament\Forms;
use Filament\Resources\Form;
use Filament\Resources\Resource;
use Filament\Resources\Table;
use Filament\Tables;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class PegawaiJabatanKpiResource extends Resource
{
    protected static ?string $model = PegawaiJabatanKpi::class;

    protected static ?string $navigationIcon = 'heroicon-o-collection';
    protected static ?string $navigationGroup = 'Kepegawaian';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('id_pegawai_jabatan')
                    ->required(),
                Forms\Components\TextInput::make('id_jabatan_kpi')
                    ->required(),
                Forms\Components\TextInput::make('capaian')
                    ->required(),
                Forms\Components\TextInput::make('persentase_capaian')
                    ->required(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('id_pegawai_jabatan'),
                Tables\Columns\TextColumn::make('id_jabatan_kpi'),
                Tables\Columns\TextColumn::make('capaian'),
                Tables\Columns\TextColumn::make('persentase_capaian'),
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
            'index' => Pages\ManagePegawaiJabatanKpis::route('/'),
        ];
    }    
}
