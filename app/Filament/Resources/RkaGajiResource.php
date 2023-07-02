<?php

namespace App\Filament\Resources;

use Closure;
use Filament\Forms;
use App\Models\Akun;
use App\Models\Unit;
use Filament\Tables;
use App\Models\RkaGaji;
use App\Models\Setting;
use Filament\Resources\Form;
use Filament\Resources\Table;
use Filament\Resources\Resource;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\Section;
use Filament\Tables\Columns\TextColumn;
use Filament\Forms\Components\TextInput;
use Filament\Tables\Columns\SelectColumn;
use Illuminate\Database\Eloquent\Builder;
use Filament\Tables\Columns\TextInputColumn;
use App\Filament\Resources\RkaGajiResource\Pages;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use App\Filament\Resources\RkaGajiResource\RelationManagers;
use AlperenErsoy\FilamentExport\Actions\FilamentExportHeaderAction;

class RkaGajiResource extends Resource
{
    protected static ?string $model = RkaGaji::class;

    protected static ?string $modelLabel = 'Rka Gaji';

    protected static ?string $navigationIcon = 'heroicon-o-chart-bar';

    protected static ?string $slug = 'rka-gaji';

    protected static ?string $navigationLabel = 'RKA Gaji';

    protected static ?string $navigationGroup = 'Rencana Kerja & Anggaran';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                TextInput::make('nomor_akun')
                    ->maxLength(255)
                    ->hidden(),
                TextInput::make('uraian')
                    ->required()
                    ->maxLength(255),
                Select::make('unit_nama')
                    ->required()
                    ->options(
                        Unit::all()->pluck('nama', 'id')
                    )->default(auth()->user()->unit_id)
                    ->disabled(!(auth()->user()->hasRole('superadmin'))),
                TextInput::make('total')
                    ->numeric()
                    ->mask(fn (TextInput\Mask $mask) => $mask
                        ->numeric()
                        ->decimalPlaces(2)
                        ->decimalSeparator('.')
                        ->thousandsSeparator(','),),
                Toggle::make('is_detail')
                    ->required()
                    ->hidden(fn (Closure $get) => !($get('total') > 0))
                    ->reactive()
                    ->label('Buat Rincian Bulanan'),
                Section::make('Rencana Bulanan')
                    ->schema([
                        TextInput::make('januari')
                            ->numeric()
                            ->default(0)
                            ->mask(fn (TextInput\Mask $mask) => $mask
                                ->numeric()
                                ->decimalPlaces(2)
                                ->decimalSeparator('.')
                                ->thousandsSeparator(','),),
                        TextInput::make('februari')
                            ->numeric()
                            ->default(0)
                            ->mask(fn (TextInput\Mask $mask) => $mask
                                ->numeric()
                                ->decimalPlaces(2)
                                ->decimalSeparator('.')
                                ->thousandsSeparator(','),),
                        TextInput::make('maret')
                            ->numeric()
                            ->default(0)
                            ->mask(fn (TextInput\Mask $mask) => $mask
                                ->numeric()
                                ->decimalPlaces(2)
                                ->decimalSeparator('.')
                                ->thousandsSeparator(','),),
                        TextInput::make('april')
                            ->numeric()
                            ->default(0)
                            ->mask(fn (TextInput\Mask $mask) => $mask
                                ->numeric()
                                ->decimalPlaces(2)
                                ->decimalSeparator('.')
                                ->thousandsSeparator(','),),
                        TextInput::make('mei')
                            ->numeric()
                            ->default(0)
                            ->mask(fn (TextInput\Mask $mask) => $mask
                                ->numeric()
                                ->decimalPlaces(2)
                                ->decimalSeparator('.')
                                ->thousandsSeparator(','),),
                        TextInput::make('juni')
                            ->numeric()
                            ->default(0)
                            ->mask(fn (TextInput\Mask $mask) => $mask
                                ->numeric()
                                ->decimalPlaces(2)
                                ->decimalSeparator('.')
                                ->thousandsSeparator(','),),
                        TextInput::make('juli')
                            ->numeric()
                            ->default(0)
                            ->mask(fn (TextInput\Mask $mask) => $mask
                                ->numeric()
                                ->decimalPlaces(2)
                                ->decimalSeparator('.')
                                ->thousandsSeparator(','),),
                        TextInput::make('agustus')
                            ->numeric()
                            ->default(0)
                            ->mask(fn (TextInput\Mask $mask) => $mask
                                ->numeric()
                                ->decimalPlaces(2)
                                ->decimalSeparator('.')
                                ->thousandsSeparator(','),),
                        TextInput::make('september')
                            ->numeric()
                            ->default(0)
                            ->mask(fn (TextInput\Mask $mask) => $mask
                                ->numeric()
                                ->decimalPlaces(2)
                                ->decimalSeparator('.')
                                ->thousandsSeparator(','),),
                        TextInput::make('oktober')
                            ->numeric()
                            ->default(0)
                            ->mask(fn (TextInput\Mask $mask) => $mask
                                ->numeric()
                                ->decimalPlaces(2)
                                ->decimalSeparator('.')
                                ->thousandsSeparator(','),),
                        TextInput::make('november')
                            ->numeric()
                            ->default(0)
                            ->mask(fn (TextInput\Mask $mask) => $mask
                                ->numeric()
                                ->decimalPlaces(2)
                                ->decimalSeparator('.')
                                ->thousandsSeparator(','),),
                        TextInput::make('desember')
                            ->numeric()
                            ->default(0)
                            ->mask(fn (TextInput\Mask $mask) => $mask
                                ->numeric()
                                ->decimalPlaces(2)
                                ->decimalSeparator('.')
                                ->thousandsSeparator(','),),
                    ])->hidden(function (Closure $get) {
                        return (!$get('is_detail'));
                    }),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                (Setting::find(2)->valbool) ? TextColumn::make('nomor_akun')->formatStateUsing(function (RkaGaji $record) {
                    return (($record->is_parent) ? '' : 'Gaj-' . str_pad($record->id, 6, '0', STR_PAD_LEFT));
                }) : ((auth()->user()->hasRole(['superadmin', 'admin-keuangan'])) ? SelectColumn::make('nomor_akun')->options(Akun::all()->pluck('nama', 'id')) : TextColumn::make('nomor_akun')),
                (auth()->user()->is_editmode) ? TextInputColumn::make('uraian') : TextColumn::make('uraian')
                    ->searchable(),
                TextColumn::make('unit_nama')
                    ->label('Unit')
                    ->formatStateUsing(fn ($state) => Unit::find($state)->nama),
                (auth()->user()->is_editmode) ? TextInputColumn::make('total')->alignRight()->rules(['required', 'numeric'])->type('number') : TextColumn::make('total')
                    ->formatStateUsing(fn ($state) => ($state !== null) ? number_format((float)$state) : null)
                    ->alignRight()->color(function (RkaGaji $record) {
                        if (!$record->is_parent) {
                            $data = RkaGaji::find($record->id); // Replace with your actual record id

                            $total = floatval($data->januari) + floatval($data->februari) + floatval($data->maret) + floatval($data->april)
                                + floatval($data->mei) + floatval($data->juni) + floatval($data->juli) + floatval($data->agustus)
                                + floatval($data->september) + floatval($data->oktober) + floatval($data->november)
                                + floatval($data->desember);

                            if ($record->total < $total) {
                                return 'danger';
                            } else if ($record->total > $total) {
                                return 'primary';
                            }
                        }
                    }),
                (auth()->user()->is_editmode) ? TextInputColumn::make('januari')->alignRight()->rules(['numeric'])->type('number') : TextColumn::make('januari')
                    ->formatStateUsing(function ($state) {
                       if ($state !== null) {
                            return number_format((float)$state);
                        }
                    })->sortable()->alignRight(),
                (auth()->user()->is_editmode) ? TextInputColumn::make('februari')->alignRight()->rules(['numeric'])->type('number') : TextColumn::make('februari')
                    ->formatStateUsing(function ($state) {
                        if ($state !== null) {
                            return number_format((float)$state);
                        }
                    })->sortable()->alignRight(),
                (auth()->user()->is_editmode) ? TextInputColumn::make('maret')->alignRight()->rules(['numeric'])->type('number') : TextColumn::make('maret')
                    ->formatStateUsing(function ($state) {
                        if ($state !== null) {
                            return number_format((float)$state);
                        }
                    })->sortable()->alignRight(),
                (auth()->user()->is_editmode) ? TextInputColumn::make('april')->alignRight()->rules(['numeric'])->type('number') : TextColumn::make('april')
                    ->formatStateUsing(function ($state) {
                        if ($state !== null) {
                            return number_format((float)$state);
                        }
                    })->sortable()->alignRight(),
                (auth()->user()->is_editmode) ? TextInputColumn::make('mei')->alignRight()->rules(['numeric'])->type('number') : TextColumn::make('mei')
                    ->formatStateUsing(function ($state) {
                        if ($state !== null) {
                            return number_format((float)$state);
                        }
                    })->sortable()->alignRight(),
                (auth()->user()->is_editmode) ? TextInputColumn::make('juni')->alignRight()->rules(['numeric'])->type('number') : TextColumn::make('juni')
                    ->formatStateUsing(function ($state) {
                        if ($state !== null) {
                            return number_format((float)$state);
                        }
                    })->sortable()->alignRight(),
                (auth()->user()->is_editmode) ? TextInputColumn::make('juli')->alignRight()->rules(['numeric'])->type('number') : TextColumn::make('juli')
                    ->formatStateUsing(function ($state) {
                        if ($state !== null) {
                            return number_format((float)$state);
                        }
                    })->sortable()->alignRight(),
                (auth()->user()->is_editmode) ? TextInputColumn::make('agustus')->alignRight()->rules(['numeric'])->type('number') : TextColumn::make('agustus')
                    ->formatStateUsing(function ($state) {
                        if ($state !== null) {
                            return number_format((float)$state);
                        }
                    })->sortable()->alignRight(),
                (auth()->user()->is_editmode) ? TextInputColumn::make('september')->alignRight()->rules(['numeric'])->type('number') : TextColumn::make('september')
                    ->formatStateUsing(function ($state) {
                        if ($state !== null) {
                            return number_format((float)$state);
                        }
                    })->sortable()->alignRight(),
                (auth()->user()->is_editmode) ? TextInputColumn::make('oktober')->alignRight()->rules(['numeric'])->type('number') : TextColumn::make('oktober')
                    ->formatStateUsing(function ($state) {
                        if ($state !== null) {
                            return number_format((float)$state);
                        }
                    })->sortable()->alignRight(),
                (auth()->user()->is_editmode) ? TextInputColumn::make('november')->alignRight()->rules(['numeric'])->type('number') : TextColumn::make('november')
                    ->formatStateUsing(function ($state) {
                        if ($state !== null) {
                            return number_format((float)$state);
                        }
                    })->sortable()->alignRight(),
                (auth()->user()->is_editmode) ? TextInputColumn::make('desember')->alignRight()->rules(['numeric'])->type('number') : TextColumn::make('desember')
                    ->formatStateUsing(function ($state) {
                        if ($state !== null) {
                            return number_format((float)$state);
                        }
                    })->sortable()->alignRight(),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime(),
                Tables\Columns\TextColumn::make('updated_at')
                    ->dateTime(),
            ])
            ->filters([
                //
            ])
            ->actions([
                Tables\Actions\Action::make('duplikat')->action(function (RkaGaji $record) {
                    $data = RkaGaji::find($record->id);
                    $newData = $data->replicate();
                    $newData->uraian = $data->uraian . ' COPY';
                    $newData->save();
                })->icon('heroicon-s-duplicate')->hidden(auth()->user()->hasRole('admin-keuangan') || Setting::find(1)->valbool),
                Tables\Actions\ViewAction::make()->hidden(!(auth()->user()->hasRole('admin-keuangan') || Setting::find(1)->valbool)),
                Tables\Actions\EditAction::make()->hidden(auth()->user()->hasRole('admin-keuangan') || Setting::find(1)->valbool),
                Tables\Actions\DeleteAction::make()->hidden(auth()->user()->hasRole('admin-keuangan') || Setting::find(1)->valbool),
            ])
            ->bulkActions([
                Tables\Actions\DeleteBulkAction::make()->hidden(auth()->user()->hasRole('admin-keuangan') || Setting::find(1)->valbool),
            ])
            ->headerActions([
                FilamentExportHeaderAction::make('Export'),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ManageRkaGajis::route('/'),
        ];
    }
}
