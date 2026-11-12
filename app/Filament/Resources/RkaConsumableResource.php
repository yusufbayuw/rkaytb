<?php

namespace App\Filament\Resources;

use Filament\Actions;

use Closure;
use App\Models\RkaConsumable;
use Filament\Forms;
use App\Models\Akun;
use App\Models\Unit;
use Filament\Tables;
use Filament\Schemas\Schema as Form;
use Filament\Tables\Table;
use Filament\Resources\Resource;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Forms\Components\TextInput;
use Filament\Tables\Columns\SelectColumn;
use Illuminate\Database\Eloquent\Builder;
use Filament\Tables\Columns\TextInputColumn;
use App\Filament\Resources\RkaConsumableResource\Pages;
use Illuminate\Contracts\Pagination\Paginator;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use App\Filament\Resources\RkaConsumableResource\RelationManagers;
use App\Filament\Actions\LegacyExportAction;
use App\Models\Setting;

class RkaConsumableResource extends Resource
{
    public static function canViewAny(): bool
    {
        return \App\Support\LegacyRkaAccess::canView(auth()->user())
            && parent::canViewAny();
    }

    public static function canCreate(): bool
    {
        return \App\Support\LegacyRkaAccess::canWrite(auth()->user())
            && parent::canCreate();
    }

    public static function canView(\Illuminate\Database\Eloquent\Model $record): bool
    {
        return \App\Support\LegacyRkaAccess::canAccessRecord(auth()->user(), $record, false)
            && parent::canView($record);
    }

    public static function canEdit(\Illuminate\Database\Eloquent\Model $record): bool
    {
        return \App\Support\LegacyRkaAccess::canWrite(auth()->user()) && \App\Support\LegacyRkaAccess::canAccessRecord(auth()->user(), $record, false)
            && parent::canEdit($record);
    }

    public static function canDelete(\Illuminate\Database\Eloquent\Model $record): bool
    {
        return \App\Support\LegacyRkaAccess::canWrite(auth()->user()) && \App\Support\LegacyRkaAccess::canAccessRecord(auth()->user(), $record, false)
            && parent::canDelete($record);
    }

    public static function shouldRegisterNavigation(): bool
    {
        return self::canViewAny() && parent::shouldRegisterNavigation();
    }
    protected static ?string $model = RkaConsumable::class;

    protected static ?string $modelLabel = 'Rka Consumable';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-shopping-cart';

    protected static ?string $slug = 'rka-consumable';

    protected static ?string $navigationLabel = 'RKA Consumable';

    protected static string|\UnitEnum|null $navigationGroup = 'Rencana Kerja & Anggaran';

    public static function getEloquentQuery(): Builder
    {
        if (auth()->user()->hasRole('superadmin')) {
            if (auth()->user()->is_editmode) {
                return parent::getEloquentQuery()->orderBy('unit_nama', 'asc')->isLeaf();
            } else {
                return parent::getEloquentQuery()->orderBy('unit_nama', 'asc')->tree()->depthFirst();
            }
        } else {
            if (auth()->user()->is_editmode) {
                return parent::getEloquentQuery()->where('unit_nama', auth()->user()->unit_id)->isLeaf();
            } else {
                return parent::getEloquentQuery()->where('unit_nama', auth()->user()->unit_id)->tree()->depthFirst();
            }
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
                \Filament\Schemas\Components\Section::make('Uraian')
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
                            ->disabled(!(auth()->user()->hasRole('superadmin')))->dehydrated()
                            ->dehydrateStateUsing(fn ($state) => auth()->user()->hasRole('superadmin') ? $state : auth()->user()->unit_id),
                        Toggle::make('is_parent')
                            ->required()
                            ->default(false)
                            ->reactive()
                            ->afterStateUpdated(
                                function (\Filament\Schemas\Components\Utilities\Get $get, $set) {
                                    if ($get('is_parent')) {
                                        if ($get('parent_id') !== null) {
                                            $user = RkaConsumable::find($get('parent_id'))->ancestors;
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
                                function (\Filament\Schemas\Components\Utilities\Get $get) {
                                    if (auth()->user()->hasRole('superadmin')) {
                                        if ($get('is_parent')) {
                                            return RkaConsumable::where('is_parent', true)->where('id', '!=', ($get('id')))->where('unit_nama', $get('unit_nama'))->tree()->depthFirst()->pluck('uraian_view', 'id');
                                        } else {
                                            return RkaConsumable::where('is_parent', true)->where('unit_nama', $get('unit_nama'))->tree()->depthFirst()->pluck('uraian_view', 'id');
                                        }
                                    } else {
                                        if ($get('is_parent')) {
                                            return RkaConsumable::where('is_parent', true)->where('unit_nama', auth()->user()->unit_id)->where('id', '!=', ($get('id')))->tree()->depthFirst()->pluck('uraian_view', 'id');
                                        } else {
                                            return RkaConsumable::where('is_parent', true)->where('unit_nama', auth()->user()->unit_id)->tree()->depthFirst()->pluck('uraian_view', 'id');
                                        }
                                    }
                                }
                            )->afterStateUpdated(
                                function (\Filament\Schemas\Components\Utilities\Get $get, $set, $state) {
                                    if ($get('is_parent')) {
                                        if ($state !== null) {
                                            $user = RkaConsumable::find($state)->ancestors;
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
                \Filament\Schemas\Components\Section::make('Rincian')
                    ->schema([
                        TextInput::make('volume')
                            ->debounce('1500ms')
                            ->numeric()
                            ->afterStateUpdated(function (\Filament\Schemas\Components\Utilities\Get $get, $state, $set) {
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
                            ->options(config('conf_variable.satuan'))->searchable(),
                        TextInput::make('harga_satuan')
                            ->numeric()
                            ->debounce('1500ms')
                            ->afterStateUpdated(function (\Filament\Schemas\Components\Utilities\Get $get, $state, $set) {
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
                            ->hidden(fn (\Filament\Schemas\Components\Utilities\Get $get) => !($get('total') > 0))
                            ->reactive()
                            ->label('Buat Rincian Bulanan'),
                        \Filament\Schemas\Components\Section::make('Rencana Bulanan')
                            ->schema([
                                TextInput::make('januari')
                                    ->numeric()
                                    ->default(0),
                                TextInput::make('februari')
                                    ->numeric()
                                    ->default(0),
                                TextInput::make('maret')
                                    ->numeric()
                                    ->default(0),
                                TextInput::make('april')
                                    ->numeric()
                                    ->default(0),
                                TextInput::make('mei')
                                    ->numeric()
                                    ->default(0),
                                TextInput::make('juni')
                                    ->numeric()
                                    ->default(0),
                                TextInput::make('juli')
                                    ->numeric()
                                    ->default(0),
                                TextInput::make('agustus')
                                    ->numeric()
                                    ->default(0),
                                TextInput::make('september')
                                    ->numeric()
                                    ->default(0),
                                TextInput::make('oktober')
                                    ->numeric()
                                    ->default(0),
                                TextInput::make('november')
                                    ->numeric()
                                    ->default(0),
                                TextInput::make('desember')
                                    ->numeric()
                                    ->default(0),
                            ])->hidden(function (\Filament\Schemas\Components\Utilities\Get $get) {
                                return (!$get('is_detail'));
                            }),
                    ])->hidden(function (\Filament\Schemas\Components\Utilities\Get $get) {
                        return $get('is_parent');
                    }),


            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->paginationPageOptions([50, 100, 200, 300, 500])->defaultPaginationPageOption(50)
            ->columns([
                (Setting::flag(2)) ? TextColumn::make('nomor_akun')->formatStateUsing(function (RkaConsumable $record) {
                    return (($record->is_parent) ? '' : 'Con-' . str_pad($record->id, 6, '0', STR_PAD_LEFT));
                }) : ((auth()->user()->hasRole(['superadmin', 'admin-keuangan'])) ? SelectColumn::make('nomor_akun')->disabled(fn ($record) => !static::canEdit($record))->options(Akun::all()->pluck('nama', 'id')) : TextColumn::make('nomor_akun')),
                (auth()->user()->is_editmode) ? TextInputColumn::make('uraian')->disabled(fn ($record) => !static::canEdit($record)) : TextColumn::make('uraian')
                    ->icon(function (RkaConsumable $record) {
                        if (!$record->is_parent && $record->parent_id !== null) {
                            return 'heroicon-o-ellipsis-horizontal';
                        }
                    })
                    ->weight(function (RkaConsumable $record) {
                        if ($record->is_parent && $record->parent_id !== null) {
                            return 'medium';
                        } elseif ($record->parent_id !== null) {
                            return '';
                        } else {
                            return 'black';
                        }
                    })
                    ->size(function (RkaConsumable $record) {
                        if ($record->is_parent && $record->parent_id == null) {
                            return 'lg';
                        }
                    })->searchable(),
                TextColumn::make('unit_nama')
                    ->label('Unit')
                    ->formatStateUsing(fn ($state) => Unit::find($state)->nama),
                (auth()->user()->is_editmode) ? TextInputColumn::make('volume')->disabled(fn ($record) => !static::canEdit($record))->alignRight()->rules(['required', 'numeric'])->type('number') : TextColumn::make('volume')
                    ->formatStateUsing(fn ($state) => ($state !== null) ? number_format((float)$state) : null)
                    ->alignRight(),
                (auth()->user()->is_editmode) ? SelectColumn::make('satuan')->disabled(fn ($record) => !static::canEdit($record))->options(config('conf_variable.satuan')) : TextColumn::make('satuan'),
                (auth()->user()->is_editmode) ? TextInputColumn::make('harga_satuan')->disabled(fn ($record) => !static::canEdit($record))->alignRight()->rules(['required', 'numeric'])->type('number') : TextColumn::make('harga_satuan')
                    ->formatStateUsing(fn ($state) => ($state !== null) ? number_format((float)$state) : null)
                    ->alignRight(),
                TextColumn::make('total')
                    ->formatStateUsing(function (RkaConsumable $record, $state) {
                        if ($record->is_parent) {
                            $jumlah = $record->childrenScoreSum(); // Read-only display; totals synchronize on save.

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
                                // No database writes during table rendering. The model saves computed totals.
                                return number_format((float)$state);
                            }
                        }
                    })->sortable()
                    ->alignRight()
                    ->weight(function (RkaConsumable $record) {
                        if ($record->is_parent && $record->parent_id !== null) {
                            return 'medium';
                        } elseif ($record->parent_id !== null) {
                            return '';
                        } else {
                            return 'black';
                        }
                    })
                    ->size(function (RkaConsumable $record) {
                        if ($record->is_parent && $record->parent_id == null) {
                            return 'lg';
                        }
                    })
                    ->color(function (RkaConsumable $record) {
                        if (!$record->is_parent) {
                            $data = RkaConsumable::find($record->id); // Replace with your actual record id

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
                (auth()->user()->is_editmode) ? TextInputColumn::make('januari')->disabled(fn ($record) => !static::canEdit($record))->alignRight()->rules(['numeric'])->type('number') : TextColumn::make('januari')
                    ->formatStateUsing(function (RkaConsumable $record, $state) {
                        if ($record->is_parent) {
                            $jumlah = $record->childrenScoreSumJanuari(); // Read-only display; totals synchronize on save.

                            if ($state !== null && $state > 0) {
                                return ($jumlah !== $state) ? number_format($jumlah) : null;
                            } else {
                                return ($jumlah > 0) ? number_format($jumlah) : null;
                            }
                        } else if ($state !== null) {
                            return number_format((float)$state);
                        }
                    })->sortable()
                    ->alignRight()
                    ->weight(function (RkaConsumable $record) {
                        if ($record->is_parent && $record->parent_id !== null) {
                            return 'medium';
                        } elseif ($record->parent_id !== null) {
                            return '';
                        } else {
                            return 'black';
                        }
                    })
                    ->size(function (RkaConsumable $record) {
                        if ($record->is_parent && $record->parent_id == null) {
                            return 'lg';
                        }
                    })
                    ->alignRight(),
                (auth()->user()->is_editmode) ? TextInputColumn::make('februari')->disabled(fn ($record) => !static::canEdit($record))->alignRight()->rules(['numeric'])->type('number') : TextColumn::make('februari')
                    ->formatStateUsing(function (RkaConsumable $record, $state) {
                        if ($record->is_parent) {
                            $jumlah = $record->childrenScoreSumFebruari(); // Read-only display; totals synchronize on save.

                            if ($state !== null && $state > 0) {
                                return ($jumlah !== $state) ? number_format($jumlah) : null;
                            } else {
                                return ($jumlah > 0) ? number_format($jumlah) : null;
                            }
                        } else if ($state !== null) {
                            return number_format((float)$state);
                        }
                    })->sortable()
                    ->alignRight()
                    ->weight(function (RkaConsumable $record) {
                        if ($record->is_parent && $record->parent_id !== null) {
                            return 'medium';
                        } elseif ($record->parent_id !== null) {
                            return '';
                        } else {
                            return 'black';
                        }
                    })
                    ->size(function (RkaConsumable $record) {
                        if ($record->is_parent && $record->parent_id == null) {
                            return 'lg';
                        }
                    })
                    ->alignRight(),
                (auth()->user()->is_editmode) ? TextInputColumn::make('maret')->disabled(fn ($record) => !static::canEdit($record))->alignRight()->rules(['numeric'])->type('number') : TextColumn::make('maret')
                    ->formatStateUsing(function (RkaConsumable $record, $state) {
                        if ($record->is_parent) {
                            $jumlah = $record->childrenScoreSumMaret(); // Read-only display; totals synchronize on save.

                            if ($state !== null && $state > 0) {
                                return ($jumlah !== $state) ? number_format($jumlah) : null;
                            } else {
                                return ($jumlah > 0) ? number_format($jumlah) : null;
                            }
                        } else if ($state !== null) {
                            return number_format((float)$state);
                        }
                    })->sortable()
                    ->alignRight()
                    ->weight(function (RkaConsumable $record) {
                        if ($record->is_parent && $record->parent_id !== null) {
                            return 'medium';
                        } elseif ($record->parent_id !== null) {
                            return '';
                        } else {
                            return 'black';
                        }
                    })
                    ->size(function (RkaConsumable $record) {
                        if ($record->is_parent && $record->parent_id == null) {
                            return 'lg';
                        }
                    })
                    ->alignRight(),
                (auth()->user()->is_editmode) ? TextInputColumn::make('april')->disabled(fn ($record) => !static::canEdit($record))->alignRight()->rules(['numeric'])->type('number') : TextColumn::make('april')
                    ->formatStateUsing(function (RkaConsumable $record, $state) {
                        if ($record->is_parent) {
                            $jumlah = $record->childrenScoreSumApril(); // Read-only display; totals synchronize on save.

                            if ($state !== null && $state > 0) {
                                return ($jumlah !== $state) ? number_format($jumlah) : null;
                            } else {
                                return ($jumlah > 0) ? number_format($jumlah) : null;
                            }
                        } else if ($state !== null) {
                            return number_format((float)$state);
                        }
                    })->sortable()
                    ->alignRight()
                    ->weight(function (RkaConsumable $record) {
                        if ($record->is_parent && $record->parent_id !== null) {
                            return 'medium';
                        } elseif ($record->parent_id !== null) {
                            return '';
                        } else {
                            return 'black';
                        }
                    })
                    ->size(function (RkaConsumable $record) {
                        if ($record->is_parent && $record->parent_id == null) {
                            return 'lg';
                        }
                    })
                    ->alignRight(),
                (auth()->user()->is_editmode) ? TextInputColumn::make('mei')->disabled(fn ($record) => !static::canEdit($record))->alignRight()->rules(['numeric'])->type('number') : TextColumn::make('mei')
                    ->formatStateUsing(function (RkaConsumable $record, $state) {
                        if ($record->is_parent) {
                            $jumlah = $record->childrenScoreSumMei(); // Read-only display; totals synchronize on save.

                            if ($state !== null && $state > 0) {
                                return ($jumlah !== $state) ? number_format($jumlah) : null;
                            } else {
                                return ($jumlah > 0) ? number_format($jumlah) : null;
                            }
                        } else if ($state !== null) {
                            return number_format((float)$state);
                        }
                    })->sortable()
                    ->alignRight()
                    ->weight(function (RkaConsumable $record) {
                        if ($record->is_parent && $record->parent_id !== null) {
                            return 'medium';
                        } elseif ($record->parent_id !== null) {
                            return '';
                        } else {
                            return 'black';
                        }
                    })
                    ->size(function (RkaConsumable $record) {
                        if ($record->is_parent && $record->parent_id == null) {
                            return 'lg';
                        }
                    })
                    ->alignRight(),
                (auth()->user()->is_editmode) ? TextInputColumn::make('juni')->disabled(fn ($record) => !static::canEdit($record))->alignRight()->rules(['numeric'])->type('number') : TextColumn::make('juni')
                    ->formatStateUsing(function (RkaConsumable $record, $state) {
                        if ($record->is_parent) {
                            $jumlah = $record->childrenScoreSumJuni(); // Read-only display; totals synchronize on save.

                            if ($state !== null && $state > 0) {
                                return ($jumlah !== $state) ? number_format($jumlah) : null;
                            } else {
                                return ($jumlah > 0) ? number_format($jumlah) : null;
                            }
                        } else if ($state !== null) {
                            return number_format((float)$state);
                        }
                    })->sortable()
                    ->alignRight()
                    ->weight(function (RkaConsumable $record) {
                        if ($record->is_parent && $record->parent_id !== null) {
                            return 'medium';
                        } elseif ($record->parent_id !== null) {
                            return '';
                        } else {
                            return 'black';
                        }
                    })
                    ->size(function (RkaConsumable $record) {
                        if ($record->is_parent && $record->parent_id == null) {
                            return 'lg';
                        }
                    })
                    ->alignRight(),
                (auth()->user()->is_editmode) ? TextInputColumn::make('juli')->disabled(fn ($record) => !static::canEdit($record))->alignRight()->rules(['numeric'])->type('number') : TextColumn::make('juli')
                    ->formatStateUsing(function (RkaConsumable $record, $state) {
                        if ($record->is_parent) {
                            $jumlah = $record->childrenScoreSumJuli(); // Read-only display; totals synchronize on save.

                            if ($state !== null && $state > 0) {
                                return ($jumlah !== $state) ? number_format($jumlah) : null;
                            } else {
                                return ($jumlah > 0) ? number_format($jumlah) : null;
                            }
                        } else if ($state !== null) {
                            return number_format((float)$state);
                        }
                    })->sortable()
                    ->alignRight()
                    ->weight(function (RkaConsumable $record) {
                        if ($record->is_parent && $record->parent_id !== null) {
                            return 'medium';
                        } elseif ($record->parent_id !== null) {
                            return '';
                        } else {
                            return 'black';
                        }
                    })
                    ->size(function (RkaConsumable $record) {
                        if ($record->is_parent && $record->parent_id == null) {
                            return 'lg';
                        }
                    })
                    ->alignRight(),
                (auth()->user()->is_editmode) ? TextInputColumn::make('agustus')->disabled(fn ($record) => !static::canEdit($record))->alignRight()->rules(['numeric'])->type('number') : TextColumn::make('agustus')
                    ->formatStateUsing(function (RkaConsumable $record, $state) {
                        if ($record->is_parent) {
                            $jumlah = $record->childrenScoreSumAgustus(); // Read-only display; totals synchronize on save.

                            if ($state !== null && $state > 0) {
                                return ($jumlah !== $state) ? number_format($jumlah) : null;
                            } else {
                                return ($jumlah > 0) ? number_format($jumlah) : null;
                            }
                        } else if ($state !== null) {
                            return number_format((float)$state);
                        }
                    })->sortable()
                    ->alignRight()
                    ->weight(function (RkaConsumable $record) {
                        if ($record->is_parent && $record->parent_id !== null) {
                            return 'medium';
                        } elseif ($record->parent_id !== null) {
                            return '';
                        } else {
                            return 'black';
                        }
                    })
                    ->size(function (RkaConsumable $record) {
                        if ($record->is_parent && $record->parent_id == null) {
                            return 'lg';
                        }
                    })
                    ->alignRight(),
                (auth()->user()->is_editmode) ? TextInputColumn::make('september')->disabled(fn ($record) => !static::canEdit($record))->alignRight()->rules(['numeric'])->type('number') : TextColumn::make('september')
                    ->formatStateUsing(function (RkaConsumable $record, $state) {
                        if ($record->is_parent) {
                            $jumlah = $record->childrenScoreSumSeptember(); // Read-only display; totals synchronize on save.

                            if ($state !== null && $state > 0) {
                                return ($jumlah !== $state) ? number_format($jumlah) : null;
                            } else {
                                return ($jumlah > 0) ? number_format($jumlah) : null;
                            }
                        } else if ($state !== null) {
                            return number_format((float)$state);
                        }
                    })->sortable()
                    ->alignRight()
                    ->weight(function (RkaConsumable $record) {
                        if ($record->is_parent && $record->parent_id !== null) {
                            return 'medium';
                        } elseif ($record->parent_id !== null) {
                            return '';
                        } else {
                            return 'black';
                        }
                    })
                    ->size(function (RkaConsumable $record) {
                        if ($record->is_parent && $record->parent_id == null) {
                            return 'lg';
                        }
                    })
                    ->alignRight(),
                (auth()->user()->is_editmode) ? TextInputColumn::make('oktober')->disabled(fn ($record) => !static::canEdit($record))->alignRight()->rules(['numeric'])->type('number') : TextColumn::make('oktober')
                    ->formatStateUsing(function (RkaConsumable $record, $state) {
                        if ($record->is_parent) {
                            $jumlah = $record->childrenScoreSumOktober(); // Read-only display; totals synchronize on save.

                            if ($state !== null && $state > 0) {
                                return ($jumlah !== $state) ? number_format($jumlah) : null;
                            } else {
                                return ($jumlah > 0) ? number_format($jumlah) : null;
                            }
                        } else if ($state !== null) {
                            return number_format((float)$state);
                        }
                    })->sortable()
                    ->alignRight()
                    ->weight(function (RkaConsumable $record) {
                        if ($record->is_parent && $record->parent_id !== null) {
                            return 'medium';
                        } elseif ($record->parent_id !== null) {
                            return '';
                        } else {
                            return 'black';
                        }
                    })
                    ->size(function (RkaConsumable $record) {
                        if ($record->is_parent && $record->parent_id == null) {
                            return 'lg';
                        }
                    })
                    ->alignRight(),
                (auth()->user()->is_editmode) ? TextInputColumn::make('november')->disabled(fn ($record) => !static::canEdit($record))->alignRight()->rules(['numeric'])->type('number') : TextColumn::make('november')
                    ->formatStateUsing(function (RkaConsumable $record, $state) {
                        if ($record->is_parent) {
                            $jumlah = $record->childrenScoreSumNovember(); // Read-only display; totals synchronize on save.

                            if ($state !== null && $state > 0) {
                                return ($jumlah !== $state) ? number_format($jumlah) : null;
                            } else {
                                return ($jumlah > 0) ? number_format($jumlah) : null;
                            }
                        } else if ($state !== null) {
                            return number_format((float)$state);
                        }
                    })->sortable()
                    ->alignRight()
                    ->weight(function (RkaConsumable $record) {
                        if ($record->is_parent && $record->parent_id !== null) {
                            return 'medium';
                        } elseif ($record->parent_id !== null) {
                            return '';
                        } else {
                            return 'black';
                        }
                    })
                    ->size(function (RkaConsumable $record) {
                        if ($record->is_parent && $record->parent_id == null) {
                            return 'lg';
                        }
                    })
                    ->alignRight(),
                (auth()->user()->is_editmode) ? TextInputColumn::make('desember')->disabled(fn ($record) => !static::canEdit($record))->alignRight()->rules(['numeric'])->type('number') : TextColumn::make('desember')
                    ->formatStateUsing(function (RkaConsumable $record, $state) {
                        if ($record->is_parent) {
                            $jumlah = $record->childrenScoreSumDesember(); // Read-only display; totals synchronize on save.

                            if ($state !== null && $state > 0) {
                                return ($jumlah !== $state) ? number_format($jumlah) : null;
                            } else {
                                return ($jumlah > 0) ? number_format($jumlah) : null;
                            }
                        } else if ($state !== null) {
                            return number_format((float)$state);
                        }
                    })->sortable()
                    ->alignRight()
                    ->weight(function (RkaConsumable $record) {
                        if ($record->is_parent && $record->parent_id !== null) {
                            return 'medium';
                        } elseif ($record->parent_id !== null) {
                            return '';
                        } else {
                            return 'black';
                        }
                    })
                    ->size(function (RkaConsumable $record) {
                        if ($record->is_parent && $record->parent_id == null) {
                            return 'lg';
                        }
                    })
                    ->alignRight(),
                TextColumn::make('created_at')
                    ->dateTime()->toggleable(),
                TextColumn::make('updated_at')
                    ->dateTime()->toggleable(),
            ])
            ->filters([
                //
            ])
            ->recordActions([
                Actions\Action::make('duplikat')->action(function (RkaConsumable $record) {
                    abort_unless(static::canEdit($record), 403);
                    $data = RkaConsumable::find($record->id);
                    $newData = $data->replicate(['depth', 'path']);
                    \MrAdder\FilamentLogger\Support\ReplicationContextStore::remember($newData, $data);
                    $newData->uraian = $data->uraian . ' COPY';
                    $newData->save();
                })->icon('heroicon-s-document-duplicate')->hidden(auth()->user()->hasRole('admin-keuangan') || Setting::flag(1)),
                Actions\ViewAction::make()->hidden(!(auth()->user()->hasRole('admin-keuangan') || Setting::flag(1))),
                Actions\EditAction::make()->hidden(auth()->user()->hasRole('admin-keuangan') || Setting::flag(1)),
                Actions\DeleteAction::make()->hidden(auth()->user()->hasRole('admin-keuangan') || Setting::flag(1)),
            ])
            ->toolbarActions([
                Actions\DeleteBulkAction::make()->hidden(auth()->user()->hasRole('admin-keuangan') || Setting::flag(1)),
            ])
            ->headerActions([
                LegacyExportAction::make('export'),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ManageRkaConsumables::route('/'),
        ];
    }
}
