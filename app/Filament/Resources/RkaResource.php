<?php

namespace App\Filament\Resources;

use Closure;
use App\Models\Rka;
use Filament\Forms;
use App\Models\Akun;
use App\Models\Unit;
use Filament\Tables;
use App\Models\Setting;
use Filament\Resources\Form;
use Filament\Resources\Table;
use Filament\Resources\Resource;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Illuminate\Database\Eloquent\Model;
use Filament\Forms\Components\TextInput;
use Filament\Tables\Columns\Layout\Split;
use Filament\Tables\Columns\Layout\Stack;
use Filament\Tables\Columns\SelectColumn;
use Illuminate\Database\Eloquent\Builder;
use Filament\Tables\Columns\TextInputColumn;
use App\Filament\Resources\RkaResource\Pages;
use Illuminate\Contracts\Pagination\Paginator;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use App\Filament\Resources\RkaResource\RelationManagers;
use AlperenErsoy\FilamentExport\Actions\FilamentExportHeaderAction;

class RkaResource extends Resource
{
    protected static ?string $model = Rka::class;

    protected static ?string $modelLabel = 'RKA Investasi';

    protected static ?string $navigationIcon = 'heroicon-o-trending-up';

    protected static ?string $slug = 'rka-investasi';

    protected static ?string $navigationLabel = 'RKA Investasi';

    protected static ?string $navigationGroup = 'Rencana Kerja & Anggaran';

    public static function getEloquentQuery(): Builder
    {
        // Mendapatkan user sekaligus untuk menghindari pemanggilan berulang ke auth()->user()
        $user = auth()->user();

        $query = parent::getEloquentQuery();

        // Jika user bukan superadmin, tambahkan filter unit_nama ke query
        if (!$user->hasRole('superadmin')) {
            $query->where('unit_nama', $user->unit_id);
        }

        // Jika user dalam mode edit, filter isLeaf, jika tidak filter tree dan depthFirst
        if ($user->is_editmode) {
            $query->isLeaf();
        } else {
            $query->tree()->orderBy('path', 'desc');
            $query->depthFirst();
        }

        if ($user->hasRole('superadmin')) {
            $query->orderBy('unit_nama', 'asc');
        }

        return $query; 
    }


    private static function terbilang($angka)
    {
        $angka = abs($angka);
        $bilangan = array('', 'satu', 'dua', 'tiga', 'empat', 'lima', 'enam', 'tujuh', 'delapan', 'sembilan', 'sepuluh', 'sebelas');

        if ($angka < 12) {
            return $bilangan[$angka];
        } else if ($angka < 20) {
            return $bilangan[$angka - 10] . ' belas';
        } else if ($angka < 100) {
            $hasil_bagi = (int)($angka / 10);
            $hasil_mod = $angka % 10;
            return trim(sprintf('%s puluh %s', $bilangan[$hasil_bagi], $bilangan[$hasil_mod]));
        } else if ($angka < 200) {
            return sprintf('seratus %s', self::terbilang($angka - 100));
        } else if ($angka < 1000) {
            $hasil_bagi = (int)($angka / 100);
            $hasil_mod = $angka % 100;
            return trim(sprintf('%s ratus %s', $bilangan[$hasil_bagi], self::terbilang($hasil_mod)));
        } else if ($angka < 2000) {
            return trim(sprintf('seribu %s', self::terbilang($angka - 1000)));
        } else if ($angka < 1000000) {
            $hasil_bagi = (int)($angka / 1000);
            $hasil_mod = $angka % 1000;
            return sprintf('%s ribu %s', self::terbilang($hasil_bagi), self::terbilang($hasil_mod));
        } else if ($angka < 1000000000) {
            $hasil_bagi = (int)($angka / 1000000);
            $hasil_mod = $angka % 1000000;
            return trim(sprintf('%s juta %s', self::terbilang($hasil_bagi), self::terbilang($hasil_mod)));
        } else if ($angka < 1000000000000) {
            $hasil_bagi = (int)($angka / 1000000000);
            $hasil_mod = $angka % 1000000000;
            return trim(sprintf('%s milyar %s', self::terbilang($hasil_bagi), self::terbilang($hasil_mod)));
        } else if ($angka < 1000000000000000) {
            $hasil_bagi = $angka / 1000000000000;
            $hasil_mod = $angka % 1000000000000;
            return trim(sprintf('%s triliun %s', self::terbilang($hasil_bagi), self::terbilang($hasil_mod)));
        } else {
            return 'Angka terlalu besar';
        }
    }

    private static function buatMonthInput($month)
    {
        return TextInput::make($month)
            ->numeric()
            ->default(0)
            ->mask(
                fn (TextInput\Mask $mask) => $mask
                    ->numeric()
                    ->decimalPlaces(2)
                    ->decimalSeparator('.')
                    ->thousandsSeparator(',')
            );
    }

    private static function buatInput($name)
    {
        return TextInput::make($name)
            ->debounce('1500ms')
            ->numeric()
            ->mask(fn (TextInput\Mask $mask) => $mask
                ->numeric()
                ->decimalPlaces(2)
                ->decimalSeparator('.')
                ->thousandsSeparator(','),)
            ->afterStateUpdated(function (Closure $get, $state, $set) use ($name) {
                $other = $name === 'volume' ? 'harga_satuan' : 'volume';
                $otherValue = $get($other);
                if (($otherValue > 0) && ($state > 0)) {
                    $total_ = $otherValue * $state;
                    $set('total_view', number_format($total_, 2, '.', ','));
                    $set('total', $total_);
                    $set('total_terbilang', self::terbilang($total_));
                } else {
                    $set('total_view', 0);
                    $set('total', 0);
                    $set('total_terbilang', 'Nol');
                }
            });
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Uraian')
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
                        Toggle::make('is_parent')
                            ->required()
                            ->default(false)
                            ->reactive()
                            ->afterStateUpdated(
                                function (Closure $get, $set) {
                                    if ($get('is_parent')) {
                                        if ($get('parent_id') !== null) {
                                            $user = Rka::find($get('parent_id'))->ancestors;
                                            $depth = count($user);
                                            $set('uraian_view', str_repeat('-', $depth + 1) . ' ' . $get('uraian'));
                                        } else {
                                            $set('uraian_view', $get('uraian'));
                                        }
                                    } else {
                                        $set('uraian_view', null);
                                    }
                                }
                            )
                            ->label('Jadikan Anggaran Induk'),
                        Select::make('parent_id')
                            ->label('Anggaran ini menginduk ke')
                            ->reactive()
                            ->options(
                                function (Closure $get) {
                                    $unit_nama = auth()->user()->hasRole('superadmin') ? $get('unit_nama') : auth()->user()->unit_id;

                                    $query = Rka::where('is_parent', true)
                                        ->where('unit_nama', $unit_nama);

                                    if ($get('is_parent')) {
                                        $query->where('id', '!=', $get('id'));
                                    }

                                    return $query->tree()
                                        ->depthFirst()
                                        ->pluck('uraian_view', 'id');
                                }

                            )->afterStateUpdated(
                                function (Closure $get, $set, $state) {
                                    if ($get('is_parent')) {
                                        if ($state !== null) {
                                            $user = Rka::find($state)->ancestors;
                                            $depth = count($user);
                                            $set('uraian_view', str_repeat('-', $depth + 1) . ' ' . $get('uraian'));
                                        } else {
                                            $set('uraian_view', $get('uraian'));
                                        }
                                    }
                                }
                            ),
                        Hidden::make('uraian_view'),

                    ]),
                Forms\Components\Section::make('Rincian')
                    ->schema([
                        self::buatInput('volume'),
                        Select::make('satuan')
                            ->options(config('conf_variable.satuan'))->searchable(),
                        self::buatInput('harga_satuan'),
                        TextInput::make('total_view')->label('Total (Rupiah)')->disabled(),
                        TextInput::make('total_terbilang')->label('Terbilang')->disabled(),
                        Hidden::make('total'),
                        Toggle::make('is_detail')
                            ->required()
                            ->hidden(fn (Closure $get) => !($get('total') > 0))
                            ->reactive()
                            ->label('Buat Rincian Bulanan'),
                        Forms\Components\Section::make('Rencana Bulanan')
                            ->schema([
                                self::buatMonthInput('januari'),
                                self::buatMonthInput('februari'),
                                self::buatMonthInput('maret'),
                                self::buatMonthInput('april'),
                                self::buatMonthInput('mei'),
                                self::buatMonthInput('juni'),
                                self::buatMonthInput('juli'),
                                self::buatMonthInput('agustus'),
                                self::buatMonthInput('september'),
                                self::buatMonthInput('oktober'),
                                self::buatMonthInput('november'),
                                self::buatMonthInput('desember'),
                            ])->hidden(function (Closure $get) {
                                return (!$get('is_detail'));
                            }),
                    ])->hidden(function (Closure $get) {
                        return $get('is_parent');
                    }),


            ]);
    }

    public static function buatColumn($month)
    {
        if (auth()->user()->is_editmode) {
            return TextInputColumn::make($month)
                ->alignRight()
                ->rules(['numeric'])
                ->type('number');
        } else {
            return TextColumn::make($month)
                ->formatStateUsing(function (Rka $record, $state) use ($month) {
                    if ($record->is_parent) {
                        $parent = Rka::find($record->id);
                        $jumlah = $parent->childrenScoreSum($month);
                        $parent->$month = $jumlah;
                        $parent->save();

                        if ($state !== null && $state > 0) {
                            return ($jumlah !== $state) ? number_format($jumlah) : null;
                        } else {
                            return ($jumlah > 0) ? number_format($jumlah) : null;
                        }
                    } else if ($state !== null) {
                        return number_format((float)$state);
                    }
                })
                ->sortable()
                ->alignRight()
                ->weight(function (Rka $record) {
                    if ($record->is_parent && $record->parent_id !== null) {
                        return 'medium';
                    } elseif ($record->parent_id !== null) {
                        return '';
                    } else {
                        return 'black';
                    }
                })
                ->size(function (Rka $record) {
                    if ($record->is_parent && $record->parent_id == null) {
                        return 'lg';
                    }
                })
                ->alignRight();
        }
    }

    public static function table(Table $table): Table
    {
        $authUser = auth()->user();
        $isEditMode = $authUser->is_editmode;
        $units = $units = Unit::all()->pluck('nama', 'id')->toArray();
        $isAdminKeuangan = $authUser->hasRole('admin-keuangan') || Setting::find(1)->valbool;
        $isSuperandKeu = $authUser->hasRole(['superadmin', 'admin-keuangan']);
        $settingValBool = Setting::find(2)->valbool;
        $akunOptions  =  Akun::all()->pluck('nama', 'id');
        return $table
            ->columns([
                ($settingValBool) ?
                    TextColumn::make('nomor_akun')->formatStateUsing(function (Rka $record) {
                        return (($record->is_parent) ? '' : 'Inv-' . str_pad($record->id, 6, '0', STR_PAD_LEFT));
                    })->grow(false)
                    : (($isSuperandKeu) ?
                        SelectColumn::make('nomor_akun')->options($akunOptions)->grow(false)
                        : TextColumn::make('nomor_akun'))->grow(false),
                ($isEditMode) ?
                    TextInputColumn::make('uraian')->grow(false)
                    :
                    TextColumn::make('uraian')
                    ->icon(function (Rka $record) {
                        if (!$record->is_parent && $record->parent_id !== null) {
                            return 'heroicon-o-dots-horizontal';
                        }
                    })->grow(false)
                    ->weight(function (Rka $record) {
                        if ($record->is_parent && $record->parent_id !== null) {
                            return 'medium';
                        } elseif ($record->parent_id !== null) {
                            return '';
                        } else {
                            return 'black';
                        }
                    })
                    ->size(function (Rka $record) {
                        if ($record->is_parent && $record->parent_id == null) {
                            return 'lg';
                        }
                    })->searchable(),
                TextColumn::make('unit_nama')
                    ->label('Unit')
                    ->formatStateUsing(fn ($state) =>  $units[$state] ?? null),
                ($isEditMode) ? TextInputColumn::make('volume')
                    ->alignRight()
                    ->rules(['required', 'numeric'])
                    ->type('number')
                    : TextColumn::make('volume')
                    ->formatStateUsing(fn ($state) => ($state !== null) ? number_format((float)$state) : null)
                    ->alignRight(),
                ($isEditMode) ? SelectColumn::make('satuan')->options(config('conf_variable.satuan')) : TextColumn::make('satuan'),
                ($isEditMode) ? TextInputColumn::make('harga_satuan')->alignRight()->rules(['required', 'numeric'])->type('number') : TextColumn::make('harga_satuan')
                    ->formatStateUsing(fn ($state) => ($state !== null) ? number_format((float)$state) : null)
                    ->alignRight(),
                TextColumn::make('total')
                    ->formatStateUsing(function (Rka $record, $state) {
                        if ($record->is_parent) {
                            //$parent = Rka::find($record->id);
                            $jumlah = $record->childrenScoreSum('total');
                            $record->total = $jumlah;
                            $record->save();

                            if ($state !== null && $state > 0) {
                                return ($jumlah !== $state) ? number_format($jumlah) : null;
                            } else {
                                return ($jumlah > 0) ? number_format($jumlah) : null;
                            }
                        } else if ($state !== null) {
                            if ($state == $record->volume * $record->harga_satuan) {
                                return number_format((float)$state);
                            } else {
                                $state = $record->volume * $record->harga_satuan;
                                $data = Rka::find($record->id);
                                $data->total = $state;
                                $data->total_view = number_format($state, 2, '.', ',');
                                $data->total_terbilang = self::terbilang($state);
                                $data->save();
                                return number_format((float)$state);
                            }
                        }
                    })->sortable()
                    ->alignRight()
                    ->weight(function (Rka $record) {
                        $isParentId = $record->parent_id;
                        $isParentAndHasParent = $record->is_parent && $isParentId !== null;
                        if ($isParentAndHasParent) {
                            return 'medium';
                        } elseif ($isParentId !== null) {
                            return '';
                        } else {
                            return 'black';
                        }
                    })
                    ->size(function (Rka $record) {
                        if ($record->is_parent && $record->parent_id == null) {
                            return 'lg';
                        }
                    })
                    ->color(function (Rka $record) {
                        if (!$record->is_parent) {
                            $data = Rka::find($record->id); // Replace with your actual record id

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
                self::buatColumn('januari'),
                self::buatColumn('februari'),
                self::buatColumn('maret'),
                self::buatColumn('april'),
                self::buatColumn('mei'),
                self::buatColumn('juni'),
                self::buatColumn('juli'),
                self::buatColumn('agustus'),
                self::buatColumn('september'),
                self::buatColumn('oktober'),
                self::buatColumn('november'),
                self::buatColumn('desember'),
                TextColumn::make('created_at')
                    ->dateTime()->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->dateTime()->toggleable(isToggledHiddenByDefault: true),
            ]) //->reorderable('order_column')
            ->filters([
                //
            ])
            ->actions([
                Tables\Actions\Action::make('duplikat')
                    ->action(function (Rka $record) {
                        $newData = $record->replicate();
                        $newData->uraian = $record->uraian . ' COPY';
                        $newData->save();
                    })
                    ->icon('heroicon-s-duplicate')
                    ->hidden($isAdminKeuangan),
                Tables\Actions\ViewAction::make()->hidden(!$isAdminKeuangan),
                Tables\Actions\EditAction::make()->hidden($isAdminKeuangan),
                Tables\Actions\DeleteAction::make()->hidden($isAdminKeuangan),
            ])
            ->bulkActions([
                Tables\Actions\DeleteBulkAction::make()->hidden($isAdminKeuangan),
            ])
            ->headerActions([
                FilamentExportHeaderAction::make('Export'),
            ]);
    }

    protected function paginateTableQuery(Builder $query): Paginator
    {
        return $query->fastPaginate($this->getTableRecordsPerPage());
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ManageRkas::route('/'),
        ];
    }
}
