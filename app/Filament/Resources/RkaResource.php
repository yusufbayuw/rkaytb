<?php

namespace App\Filament\Resources;

use Filament\Actions;

use Closure;
use App\Models\Rka;
use Filament\Forms;
use App\Models\Akun;
use App\Models\Unit;
use Filament\Tables;
use App\Models\Setting;
use Filament\Schemas\Schema as Form;
use Filament\Tables\Table;
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
use App\Filament\Actions\LegacyExportAction;

class RkaResource extends Resource
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
    protected static ?string $model = Rka::class;

    protected static ?string $modelLabel = 'RKA Investasi';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-arrow-trending-up';

    protected static ?string $slug = 'rka-investasi';

    protected static ?string $navigationLabel = 'RKA Investasi';

    protected static string|\UnitEnum|null $navigationGroup = 'Rencana Kerja & Anggaran';

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
            ->default(0);
    }

    private static function buatInput($name)
    {
        return TextInput::make($name)
            ->debounce('1500ms')
            ->numeric()
            ->afterStateUpdated(function (\Filament\Schemas\Components\Utilities\Get $get, $state, $set) use ($name) {
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
                                function (\Filament\Schemas\Components\Utilities\Get $get) {
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
                                function (\Filament\Schemas\Components\Utilities\Get $get, $set, $state) {
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
                \Filament\Schemas\Components\Section::make('Rincian')
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
                            ->hidden(fn (\Filament\Schemas\Components\Utilities\Get $get) => !($get('total') > 0))
                            ->reactive()
                            ->label('Buat Rincian Bulanan'),
                        \Filament\Schemas\Components\Section::make('Rencana Bulanan')
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
                            ])->hidden(function (\Filament\Schemas\Components\Utilities\Get $get) {
                                return (!$get('is_detail'));
                            }),
                    ])->hidden(function (\Filament\Schemas\Components\Utilities\Get $get) {
                        return $get('is_parent');
                    }),


            ]);
    }

    public static function buatColumn($month)
    {
        if (auth()->user()->is_editmode) {
            return TextInputColumn::make($month)->disabled(fn ($record) => !static::canEdit($record))
                ->alignRight()
                ->rules(['numeric'])
                ->type('number');
        } else {
            return TextColumn::make($month)
                ->formatStateUsing(function (Rka $record, $state) use ($month) {
                    if ($record->is_parent) {
                        // Display aggregates must never persist data or touch updated_at.
                        $jumlah = $record->childrenScoreSum($month);

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
        $isAdminKeuangan = $authUser->hasRole('admin-keuangan') || Setting::flag(1);
        $isSuperandKeu = $authUser->hasRole(['superadmin', 'admin-keuangan']);
        $settingValBool = Setting::flag(2);
        $akunOptions  =  Akun::all()->pluck('nama', 'id');
        return $table
            ->paginationPageOptions([50, 100, 200, 300, 500])->defaultPaginationPageOption(50)
            ->columns([
                ($settingValBool) ?
                    TextColumn::make('nomor_akun')->formatStateUsing(function (Rka $record) {
                        return (($record->is_parent) ? '' : 'Inv-' . str_pad($record->id, 6, '0', STR_PAD_LEFT));
                    })->grow(false)
                    : (($isSuperandKeu) ?
                        SelectColumn::make('nomor_akun')->disabled(fn ($record) => !static::canEdit($record))->options($akunOptions)->grow(false)
                        : TextColumn::make('nomor_akun'))->grow(false),
                ($isEditMode) ?
                    TextInputColumn::make('uraian')->disabled(fn ($record) => !static::canEdit($record))->grow(false)
                    :
                    TextColumn::make('uraian')
                    ->icon(function (Rka $record) {
                        if (!$record->is_parent && $record->parent_id !== null) {
                            return 'heroicon-o-ellipsis-horizontal';
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
                ($isEditMode) ? TextInputColumn::make('volume')->disabled(fn ($record) => !static::canEdit($record))
                    ->alignRight()
                    ->rules(['required', 'numeric'])
                    ->type('number')
                    : TextColumn::make('volume')
                    ->formatStateUsing(fn ($state) => ($state !== null) ? number_format((float)$state) : null)
                    ->alignRight(),
                ($isEditMode) ? SelectColumn::make('satuan')->disabled(fn ($record) => !static::canEdit($record))->options(config('conf_variable.satuan')) : TextColumn::make('satuan'),
                ($isEditMode) ? TextInputColumn::make('harga_satuan')->disabled(fn ($record) => !static::canEdit($record))->alignRight()->rules(['required', 'numeric'])->type('number') : TextColumn::make('harga_satuan')
                    ->formatStateUsing(fn ($state) => ($state !== null) ? number_format((float)$state) : null)
                    ->alignRight(),
                TextColumn::make('total')
                    ->formatStateUsing(function (Rka $record, $state) {
                        if ($record->is_parent) {
                            //$parent = Rka::find($record->id);
                            $jumlah = $record->childrenScoreSum('total');
                            // Derived totals are calculated for display only.

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
                                // Editing/saving must be an explicit user action, never a table render.
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
            ->recordActions([
                Actions\Action::make('duplikat')
                    ->action(function (Rka $record) {
                        $newData = $record->replicate(['depth', 'path']);
                        \MrAdder\FilamentLogger\Support\ReplicationContextStore::remember($newData, $record);
                        $newData->uraian = $record->uraian . ' COPY';
                        $newData->save();
                    })
                    ->icon('heroicon-s-document-duplicate')
                    ->hidden($isAdminKeuangan),
                Actions\ViewAction::make()->hidden(!$isAdminKeuangan),
                Actions\EditAction::make()->hidden($isAdminKeuangan),
                Actions\DeleteAction::make()->hidden($isAdminKeuangan),
            ])
            ->headerActions([
                LegacyExportAction::make('export'),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ManageRkas::route('/'),
        ];
    }
}
