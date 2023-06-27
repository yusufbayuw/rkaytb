<?php

namespace App\Filament\Resources;

use Closure;
use App\Models\Rka;
use Filament\Forms;
use Filament\Tables;
use Filament\Resources\Form;
use Filament\Resources\Table;
use Filament\Resources\Resource;
use Illuminate\Database\Eloquent\Builder;
use App\Filament\Resources\RkaResource\Pages;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use App\Filament\Resources\RkaResource\RelationManagers;

class RkaResource extends Resource
{
    protected static ?string $model = Rka::class;

    protected static ?string $navigationIcon = 'heroicon-o-collection';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Uraian')
                    ->schema([
                        Forms\Components\TextInput::make('nomor_akun')
                            ->maxLength(255)
                            ->hidden(),
                        Forms\Components\TextInput::make('uraian')
                            ->required()
                            ->maxLength(255),
                        Forms\Components\Select::make('unit_nama')
                            ->required()
                            ->options([
                                'TK' => 'TK',
                                'SD' => 'SD',
                                'SMP' => 'SMP',
                                'SMA' => 'SMA',
                                'ASM' => 'ASM',
                                'ADM' => 'ADM'
                            ]),
                        Forms\Components\Toggle::make('is_parent')
                            ->required()
                            ->default(true)
                            ->reactive()
                            ->label('Jadikan Induk Anggaran'),

                    ]),
                Forms\Components\Section::make('Rincian')
                    ->schema([
                        Forms\Components\Select::make('parent_id')
                            ->required()
                            ->label('Anggaran Induk')
                            ->options(
                                Rka::where('is_parent', true)->pluck('uraian', 'id')
                            ),
                        Forms\Components\TextInput::make('volume')
                            ->maxLength(255),
                        Forms\Components\Select::make('satuan')
                            ->options([
                                'bulan' => 'bulan',
                                'kali' => 'kali',
                                'ls' => 'ls',
                                'bidang' => 'bidang',
                                'org' => 'org',
                                'bln' => 'bln',
                                'paket' => 'paket',
                                'dok' => 'dok',
                                'orang' => 'orang',
                                'anggota' => 'anggota',
                                'dus' => 'dus',
                                'dosen' => 'dosen',
                                'buah' => 'buah',
                                'kegiatan' => 'kegiatan',
                                'pertemuan' => 'pertemuan',
                                'tahun' => 'tahun',
                                'mahasiswa' => 'mahasiswa',
                                'parcel' => 'parcel',
                                'bungkus' => 'bungkus',
                            ])->searchable(),
                        Forms\Components\TextInput::make('harga_satuan')
                            ->numeric()
                            ->reactive(),
                        Forms\Components\TextInput::make('total')
                            ->default(function (Closure $get) {
                                if (($get('volume') > 0) && ($get('harga_satuan') > 0))
                                {
                                    return $get('volume')*$get('harga_satuan');
                                } else {
                                    return 0;
                                }
                                
                            }),
                        Forms\Components\Toggle::make('is_detail')
                            ->required()
                            ->default(false)
                            ->reactive()
                            ->label('Buat Rincian Bulanan'),
                        Forms\Components\Section::make('Rencana Bulanan')
                            ->schema([
                                Forms\Components\TextInput::make('januari'),
                                Forms\Components\TextInput::make('februari'),
                                Forms\Components\TextInput::make('maret'),
                                Forms\Components\TextInput::make('april'),
                                Forms\Components\TextInput::make('mei'),
                                Forms\Components\TextInput::make('juni'),
                                Forms\Components\TextInput::make('juli'),
                                Forms\Components\TextInput::make('agustus'),
                                Forms\Components\TextInput::make('september'),
                                Forms\Components\TextInput::make('oktober'),
                                Forms\Components\TextInput::make('november'),
                                Forms\Components\TextInput::make('desember'),
                            ])->hidden(function (Closure $get) {
                                return (!$get('is_detail'));
                            }),
                    ])->hidden(function (Closure $get) {
                        return $get('is_parent');
                    }),


            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('nomor_akun'),
                Tables\Columns\TextColumn::make('uraian'),
                Tables\Columns\IconColumn::make('is_parent')
                    ->boolean(),
                Tables\Columns\TextColumn::make('parent_id'),
                Tables\Columns\TextColumn::make('unit_nama'),
                Tables\Columns\TextColumn::make('satuan'),
                Tables\Columns\TextColumn::make('volume'),
                Tables\Columns\TextColumn::make('harga_satuan'),
                Tables\Columns\TextColumn::make('total'),
                Tables\Columns\TextColumn::make('januari'),
                Tables\Columns\TextColumn::make('februari'),
                Tables\Columns\TextColumn::make('maret'),
                Tables\Columns\TextColumn::make('april'),
                Tables\Columns\TextColumn::make('mei'),
                Tables\Columns\TextColumn::make('juni'),
                Tables\Columns\TextColumn::make('juli'),
                Tables\Columns\TextColumn::make('agustus'),
                Tables\Columns\TextColumn::make('september'),
                Tables\Columns\TextColumn::make('oktober'),
                Tables\Columns\TextColumn::make('november'),
                Tables\Columns\TextColumn::make('desember'),
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
            'index' => Pages\ManageRkas::route('/'),
        ];
    }
}
