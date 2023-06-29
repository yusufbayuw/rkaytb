<?php

namespace App\Filament\Resources;

use Closure;
use App\Models\Rka;
use Filament\Forms;
use App\Models\Unit;
use Filament\Tables;
use Filament\Resources\Form;
use Filament\Resources\Table;
use Filament\Resources\Resource;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Forms\Components\TextInput;
use Illuminate\Database\Eloquent\Builder;
use App\Filament\Resources\RkaResource\Pages;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use App\Filament\Resources\RkaResource\RelationManagers;
use AlperenErsoy\FilamentExport\Actions\FilamentExportHeaderAction;
use App\Models\Akun;
use Filament\Tables\Columns\SelectColumn;
use Filament\Tables\Columns\TextInputColumn;

class RkaResource extends Resource
{
    protected static ?string $model = Rka::class;

    protected static ?string $modelLabel = 'RKA Investasi';

    protected static ?string $navigationIcon = 'heroicon-o-chart-square-bar';

    protected static ?string $slug = 'rka-investasi';

    protected static ?string $navigationLabel = 'RKA Investasi';

    protected static ?string $navigationGroup = 'Rencana Kerja & Anggaran';

    public static function getEloquentQuery(): Builder
    {
        if (auth()->user()->hasRole('superadmin')) {
            return parent::getEloquentQuery()->orderBy('unit_nama', 'asc')->tree()->depthFirst();
        } else {
            return parent::getEloquentQuery()->where('unit_nama', auth()->user()->unit_id)->tree()->depthFirst();
        }
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
                                    if (auth()->user()->hasRole('superadmin')) {
                                        if ($get('is_parent')) {
                                            return Rka::where('is_parent', true)->where('id', '!=', ($get('id')))->tree()->depthFirst()->pluck('uraian_view', 'id');
                                        } else {
                                            return Rka::where('is_parent', true)->tree()->depthFirst()->pluck('uraian_view', 'id');
                                        }
                                    } else {
                                        if ($get('is_parent')) {
                                            return Rka::where('is_parent', true)->where('unit_nama', auth()->user()->unit_id)->where('id', '!=', ($get('id')))->tree()->depthFirst()->pluck('uraian_view', 'id');
                                        } else {
                                            return Rka::where('is_parent', true)->where('unit_nama', auth()->user()->unit_id)->tree()->depthFirst()->pluck('uraian_view', 'id');
                                        }
                                    }
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
                        TextInput::make('volume')
                            ->debounce('1500ms')
                            ->numeric()
                            ->mask(fn (TextInput\Mask $mask) => $mask
                                ->numeric()
                                ->decimalPlaces(2)
                                ->decimalSeparator('.')
                                ->thousandsSeparator(','),)
                            ->afterStateUpdated(function (Closure $get, $state, $set) {
                                $volume = $get('harga_satuan');
                                if (($volume > 0) && ($state > 0)) {
                                    $total_ = $volume * $state;
                                    $set('total_view', number_format($total_, 2, '.', ','));
                                    $set('total', $total_);
                                    $set('total_terbilang', self::terbilang($total_));
                                } else {
                                    $set('total_view', 0);
                                    $set('total', 0);
                                    $set('total_terbilang', 'Nol');
                                }
                            }),
                        Select::make('satuan')
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
                        TextInput::make('harga_satuan')
                            ->numeric()
                            ->debounce('1500ms')
                            ->mask(fn (TextInput\Mask $mask) => $mask
                                ->numeric()
                                ->decimalPlaces(2)
                                ->decimalSeparator('.')
                                ->thousandsSeparator(','),)
                            ->afterStateUpdated(function (Closure $get, $state, $set) {
                                $volume = $get('volume');
                                if (($volume > 0) && ($state > 0)) {
                                    $total_ = $volume * $state;
                                    $set('total_view', number_format($total_, 2, '.', ','));
                                    $set('total', $total_);
                                    $set('total_terbilang', self::terbilang($total_));
                                } else {
                                    $set('total_view', 0);
                                    $set('total', 0);
                                    $set('total_terbilang', 'Nol');
                                }
                            }),
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
                    ])->hidden(function (Closure $get) {
                        return $get('is_parent');
                    }),


            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                (auth()->user()->hasRole(['superadmin', 'admin-keuangan'])) ? SelectColumn::make('nomor_akun')->options(Akun::all()->pluck('nama', 'id')) : TextColumn::make('nomor_akun'),
                TextColumn::make('uraian')
                    ->icon(function (Rka $record) {
                        if (!$record->is_parent && $record->parent_id !== null) {
                            return 'heroicon-o-dots-horizontal';
                        }
                    })
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
                    }),
                TextColumn::make('unit_nama')
                    ->label('Unit')
                    ->formatStateUsing(fn ($state) => Unit::find($state)->nama),
                TextColumn::make('volume')
                    ->formatStateUsing(fn ($state) => ($state !== null) ? number_format($state) : null)
                    ->alignRight(),
                TextColumn::make('satuan'),
                TextColumn::make('harga_satuan')
                    ->formatStateUsing(fn ($state) => ($state !== null) ? number_format($state) : null)
                    ->alignRight(),
                TextColumn::make('total')
                    ->formatStateUsing(function (Rka $record, $state) {
                        if ($record->is_parent) {
                            $parent = Rka::find($record->id);
                            $jumlah = $parent->childrenScoreSum();
                            $parent->total = $jumlah;
                            $parent->save();

                            if ($state !== null && $state > 0) {
                                return ($jumlah !== $state) ? number_format($jumlah) : null;
                            } else {
                                return ($jumlah > 0) ? number_format($jumlah) : null;
                            }
                        } else if ($state !== null) {
                            return number_format($state);
                        }
                    })->sortable()
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
                    ->color(function (Rka $record) {
                        if (!$record->is_parent) {
                            $data = Rka::find($record->id); // Replace with your actual record id

                            $total = $data->januari + $data->februari + $data->maret + $data->april + $data->mei
                                + $data->juni + $data->juli + $data->agustus + $data->september
                                + $data->oktober + $data->november + $data->desember;
                            if ($record->total < $total) {
                                return 'danger';
                            } else if ($record->total > $total) {
                                return 'primary';
                            }
                        }
                    }),
                TextColumn::make('januari')
                    ->formatStateUsing(function (Rka $record, $state) {
                        if ($record->is_parent) {
                            $parent = Rka::find($record->id);
                            $jumlah = $parent->childrenScoreSumJanuari();
                            $parent->januari = $jumlah;
                            $parent->save();

                            if ($state !== null && $state > 0) {
                                return ($jumlah !== $state) ? number_format($jumlah) : null;
                            } else {
                                return ($jumlah > 0) ? number_format($jumlah) : null;
                            }
                        } else if ($state !== null) {
                            return number_format($state);
                        }
                    })->sortable()
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
                    ->alignRight(),
                TextColumn::make('februari')
                    ->formatStateUsing(function (Rka $record, $state) {
                        if ($record->is_parent) {
                            $parent = Rka::find($record->id);
                            $jumlah = $parent->childrenScoreSumFebruari();
                            $parent->februari = $jumlah;
                            $parent->save();

                            if ($state !== null && $state > 0) {
                                return ($jumlah !== $state) ? number_format($jumlah) : null;
                            } else {
                                return ($jumlah > 0) ? number_format($jumlah) : null;
                            }
                        } else if ($state !== null) {
                            return number_format($state);
                        }
                    })->sortable()
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
                    ->alignRight(),
                TextColumn::make('maret')
                    ->formatStateUsing(function (Rka $record, $state) {
                        if ($record->is_parent) {
                            $parent = Rka::find($record->id);
                            $jumlah = $parent->childrenScoreSumMaret();
                            $parent->maret = $jumlah;
                            $parent->save();

                            if ($state !== null && $state > 0) {
                                return ($jumlah !== $state) ? number_format($jumlah) : null;
                            } else {
                                return ($jumlah > 0) ? number_format($jumlah) : null;
                            }
                        } else if ($state !== null) {
                            return number_format($state);
                        }
                    })->sortable()
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
                    ->alignRight(),
                TextColumn::make('april')
                    ->formatStateUsing(function (Rka $record, $state) {
                        if ($record->is_parent) {
                            $parent = Rka::find($record->id);
                            $jumlah = $parent->childrenScoreSumApril();
                            $parent->april = $jumlah;
                            $parent->save();

                            if ($state !== null && $state > 0) {
                                return ($jumlah !== $state) ? number_format($jumlah) : null;
                            } else {
                                return ($jumlah > 0) ? number_format($jumlah) : null;
                            }
                        } else if ($state !== null) {
                            return number_format($state);
                        }
                    })->sortable()
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
                    ->alignRight(),
                TextColumn::make('mei')
                    ->formatStateUsing(function (Rka $record, $state) {
                        if ($record->is_parent) {
                            $parent = Rka::find($record->id);
                            $jumlah = $parent->childrenScoreSumMei();
                            $parent->mei = $jumlah;
                            $parent->save();

                            if ($state !== null && $state > 0) {
                                return ($jumlah !== $state) ? number_format($jumlah) : null;
                            } else {
                                return ($jumlah > 0) ? number_format($jumlah) : null;
                            }
                        } else if ($state !== null) {
                            return number_format($state);
                        }
                    })->sortable()
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
                    ->alignRight(),
                TextColumn::make('juni')
                    ->formatStateUsing(function (Rka $record, $state) {
                        if ($record->is_parent) {
                            $parent = Rka::find($record->id);
                            $jumlah = $parent->childrenScoreSumJuni();
                            $parent->juni = $jumlah;
                            $parent->save();

                            if ($state !== null && $state > 0) {
                                return ($jumlah !== $state) ? number_format($jumlah) : null;
                            } else {
                                return ($jumlah > 0) ? number_format($jumlah) : null;
                            }
                        } else if ($state !== null) {
                            return number_format($state);
                        }
                    })->sortable()
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
                    ->alignRight(),
                TextColumn::make('juli')
                    ->formatStateUsing(function (Rka $record, $state) {
                        if ($record->is_parent) {
                            $parent = Rka::find($record->id);
                            $jumlah = $parent->childrenScoreSumJuli();
                            $parent->juli = $jumlah;
                            $parent->save();

                            if ($state !== null && $state > 0) {
                                return ($jumlah !== $state) ? number_format($jumlah) : null;
                            } else {
                                return ($jumlah > 0) ? number_format($jumlah) : null;
                            }
                        } else if ($state !== null) {
                            return number_format($state);
                        }
                    })->sortable()
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
                    ->alignRight(),
                TextColumn::make('agustus')
                    ->formatStateUsing(function (Rka $record, $state) {
                        if ($record->is_parent) {
                            $parent = Rka::find($record->id);
                            $jumlah = $parent->childrenScoreSumAgustus();
                            $parent->agustus = $jumlah;
                            $parent->save();

                            if ($state !== null && $state > 0) {
                                return ($jumlah !== $state) ? number_format($jumlah) : null;
                            } else {
                                return ($jumlah > 0) ? number_format($jumlah) : null;
                            }
                        } else if ($state !== null) {
                            return number_format($state);
                        }
                    })->sortable()
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
                    ->alignRight(),
                TextColumn::make('september')
                    ->formatStateUsing(function (Rka $record, $state) {
                        if ($record->is_parent) {
                            $parent = Rka::find($record->id);
                            $jumlah = $parent->childrenScoreSumSeptember();
                            $parent->september = $jumlah;
                            $parent->save();

                            if ($state !== null && $state > 0) {
                                return ($jumlah !== $state) ? number_format($jumlah) : null;
                            } else {
                                return ($jumlah > 0) ? number_format($jumlah) : null;
                            }
                        } else if ($state !== null) {
                            return number_format($state);
                        }
                    })->sortable()
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
                    ->alignRight(),
                TextColumn::make('oktober')
                    ->formatStateUsing(function (Rka $record, $state) {
                        if ($record->is_parent) {
                            $parent = Rka::find($record->id);
                            $jumlah = $parent->childrenScoreSumOktober();
                            $parent->oktober = $jumlah;
                            $parent->save();

                            if ($state !== null && $state > 0) {
                                return ($jumlah !== $state) ? number_format($jumlah) : null;
                            } else {
                                return ($jumlah > 0) ? number_format($jumlah) : null;
                            }
                        } else if ($state !== null) {
                            return number_format($state);
                        }
                    })->sortable()
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
                    ->alignRight(),
                TextColumn::make('november')
                    ->formatStateUsing(function (Rka $record, $state) {
                        if ($record->is_parent) {
                            $parent = Rka::find($record->id);
                            $jumlah = $parent->childrenScoreSumNovember();
                            $parent->november = $jumlah;
                            $parent->save();

                            if ($state !== null && $state > 0) {
                                return ($jumlah !== $state) ? number_format($jumlah) : null;
                            } else {
                                return ($jumlah > 0) ? number_format($jumlah) : null;
                            }
                        } else if ($state !== null) {
                            return number_format($state);
                        }
                    })->sortable()
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
                    ->alignRight(),
                TextColumn::make('desember')
                    ->formatStateUsing(function (Rka $record, $state) {
                        if ($record->is_parent) {
                            $parent = Rka::find($record->id);
                            $jumlah = $parent->childrenScoreSumDesember();
                            $parent->desember = $jumlah;
                            $parent->save();

                            if ($state !== null && $state > 0) {
                                return ($jumlah !== $state) ? number_format($jumlah) : null;
                            } else {
                                return ($jumlah > 0) ? number_format($jumlah) : null;
                            }
                        } else if ($state !== null) {
                            return number_format($state);
                        } 
                    })->sortable()
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
                    ->alignRight(),
                TextColumn::make('created_at')
                    ->dateTime(),
                TextColumn::make('updated_at')
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
            ])
            ->headerActions([
                FilamentExportHeaderAction::make('Export'),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ManageRkas::route('/'),
        ];
    }
}
