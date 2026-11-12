<?php

namespace App\Filament\Resources;

use Filament\Actions;

use Closure;
use Filament\Forms;
use App\Models\Akun;
use App\Models\Unit;
use Filament\Tables;
use App\Models\RkaGaji;
use App\Models\Setting;
use Filament\Schemas\Schema as Form;
use Filament\Tables\Table;
use Filament\Resources\Resource;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Tables\Columns\TextColumn;
use Filament\Forms\Components\TextInput;
use Filament\Tables\Columns\SelectColumn;
use Illuminate\Database\Eloquent\Builder;
use Filament\Tables\Columns\TextInputColumn;
use App\Filament\Resources\RkaGajiResource\Pages;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use App\Filament\Resources\RkaGajiResource\RelationManagers;
use App\Filament\Actions\LegacyExportAction;

class RkaGajiResource extends Resource
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
        return \App\Support\LegacyRkaAccess::canAccessRecord(auth()->user(), $record, true)
            && parent::canView($record);
    }

    public static function canEdit(\Illuminate\Database\Eloquent\Model $record): bool
    {
        return \App\Support\LegacyRkaAccess::canWrite(auth()->user()) && \App\Support\LegacyRkaAccess::canAccessRecord(auth()->user(), $record, true)
            && parent::canEdit($record);
    }

    public static function canDelete(\Illuminate\Database\Eloquent\Model $record): bool
    {
        return \App\Support\LegacyRkaAccess::canWrite(auth()->user()) && \App\Support\LegacyRkaAccess::canAccessRecord(auth()->user(), $record, true)
            && parent::canDelete($record);
    }

    public static function shouldRegisterNavigation(): bool
    {
        return self::canViewAny() && parent::shouldRegisterNavigation();
    }
    protected static ?string $model = RkaGaji::class;

    protected static ?string $modelLabel = 'Rka Gaji';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-chart-bar';

    protected static ?string $slug = 'rka-gaji';

    protected static ?string $navigationLabel = 'RKA Gaji';

    protected static string|\UnitEnum|null $navigationGroup = 'Rencana Kerja & Anggaran';

    public static function getEloquentQuery(): \Illuminate\Database\Eloquent\Builder
    {
        $query = parent::getEloquentQuery();
        $user = auth()->user();

        if ($user && $user->hasAnyRole(['superadmin', 'admin-keuangan'])) {
            return $query;
        }

        // The former default query exposed the entire payroll budget to
        // ordinary unit accounts. Fail closed for unassigned users.
        if (!$user || !$user->unit_id ||
            !\App\Models\Unit::query()->whereKey($user->unit_id)->exists()) {
            return $query->whereRaw('1 = 0');
        }

        return $query->where('unit_nama', (string)$user->unit_id);
    }

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
                    ->disabled(!(auth()->user()->hasRole('superadmin')))->dehydrated()
                            ->dehydrateStateUsing(fn ($state) => auth()->user()->hasRole('superadmin') ? $state : auth()->user()->unit_id),
                TextInput::make('total')
                    ->numeric(),
                Toggle::make('is_detail')
                    ->required()
                    ->hidden(fn (\Filament\Schemas\Components\Utilities\Get $get) => !($get('total') > 0))
                    ->reactive()
                    ->label('Buat Rincian Bulanan'),
                Section::make('Rencana Bulanan')
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
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->paginationPageOptions([50, 100, 200, 300, 500])->defaultPaginationPageOption(50)
            ->columns([
                (Setting::flag(2)) ? TextColumn::make('nomor_akun')->formatStateUsing(function (RkaGaji $record) {
                    return (($record->is_parent) ? '' : 'Gaj-' . str_pad($record->id, 6, '0', STR_PAD_LEFT));
                }) : ((auth()->user()->hasRole(['superadmin', 'admin-keuangan'])) ? SelectColumn::make('nomor_akun')->disabled(fn ($record) => !static::canEdit($record))->options(Akun::all()->pluck('nama', 'id')) : TextColumn::make('nomor_akun')),
                (auth()->user()->is_editmode) ? TextInputColumn::make('uraian')->disabled(fn ($record) => !static::canEdit($record)) : TextColumn::make('uraian')
                    ->searchable(),
                TextColumn::make('unit_nama')
                    ->label('Unit')
                    ->formatStateUsing(fn ($state) => Unit::find($state)->nama),
                (auth()->user()->is_editmode) ? TextInputColumn::make('total')->disabled(fn ($record) => !static::canEdit($record))->alignRight()->rules(['required', 'numeric'])->type('number') : TextColumn::make('total')
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
                (auth()->user()->is_editmode) ? TextInputColumn::make('januari')->disabled(fn ($record) => !static::canEdit($record))->alignRight()->rules(['numeric'])->type('number') : TextColumn::make('januari')
                    ->formatStateUsing(function ($state) {
                       if ($state !== null) {
                            return number_format((float)$state);
                        }
                    })->sortable()->alignRight(),
                (auth()->user()->is_editmode) ? TextInputColumn::make('februari')->disabled(fn ($record) => !static::canEdit($record))->alignRight()->rules(['numeric'])->type('number') : TextColumn::make('februari')
                    ->formatStateUsing(function ($state) {
                        if ($state !== null) {
                            return number_format((float)$state);
                        }
                    })->sortable()->alignRight(),
                (auth()->user()->is_editmode) ? TextInputColumn::make('maret')->disabled(fn ($record) => !static::canEdit($record))->alignRight()->rules(['numeric'])->type('number') : TextColumn::make('maret')
                    ->formatStateUsing(function ($state) {
                        if ($state !== null) {
                            return number_format((float)$state);
                        }
                    })->sortable()->alignRight(),
                (auth()->user()->is_editmode) ? TextInputColumn::make('april')->disabled(fn ($record) => !static::canEdit($record))->alignRight()->rules(['numeric'])->type('number') : TextColumn::make('april')
                    ->formatStateUsing(function ($state) {
                        if ($state !== null) {
                            return number_format((float)$state);
                        }
                    })->sortable()->alignRight(),
                (auth()->user()->is_editmode) ? TextInputColumn::make('mei')->disabled(fn ($record) => !static::canEdit($record))->alignRight()->rules(['numeric'])->type('number') : TextColumn::make('mei')
                    ->formatStateUsing(function ($state) {
                        if ($state !== null) {
                            return number_format((float)$state);
                        }
                    })->sortable()->alignRight(),
                (auth()->user()->is_editmode) ? TextInputColumn::make('juni')->disabled(fn ($record) => !static::canEdit($record))->alignRight()->rules(['numeric'])->type('number') : TextColumn::make('juni')
                    ->formatStateUsing(function ($state) {
                        if ($state !== null) {
                            return number_format((float)$state);
                        }
                    })->sortable()->alignRight(),
                (auth()->user()->is_editmode) ? TextInputColumn::make('juli')->disabled(fn ($record) => !static::canEdit($record))->alignRight()->rules(['numeric'])->type('number') : TextColumn::make('juli')
                    ->formatStateUsing(function ($state) {
                        if ($state !== null) {
                            return number_format((float)$state);
                        }
                    })->sortable()->alignRight(),
                (auth()->user()->is_editmode) ? TextInputColumn::make('agustus')->disabled(fn ($record) => !static::canEdit($record))->alignRight()->rules(['numeric'])->type('number') : TextColumn::make('agustus')
                    ->formatStateUsing(function ($state) {
                        if ($state !== null) {
                            return number_format((float)$state);
                        }
                    })->sortable()->alignRight(),
                (auth()->user()->is_editmode) ? TextInputColumn::make('september')->disabled(fn ($record) => !static::canEdit($record))->alignRight()->rules(['numeric'])->type('number') : TextColumn::make('september')
                    ->formatStateUsing(function ($state) {
                        if ($state !== null) {
                            return number_format((float)$state);
                        }
                    })->sortable()->alignRight(),
                (auth()->user()->is_editmode) ? TextInputColumn::make('oktober')->disabled(fn ($record) => !static::canEdit($record))->alignRight()->rules(['numeric'])->type('number') : TextColumn::make('oktober')
                    ->formatStateUsing(function ($state) {
                        if ($state !== null) {
                            return number_format((float)$state);
                        }
                    })->sortable()->alignRight(),
                (auth()->user()->is_editmode) ? TextInputColumn::make('november')->disabled(fn ($record) => !static::canEdit($record))->alignRight()->rules(['numeric'])->type('number') : TextColumn::make('november')
                    ->formatStateUsing(function ($state) {
                        if ($state !== null) {
                            return number_format((float)$state);
                        }
                    })->sortable()->alignRight(),
                (auth()->user()->is_editmode) ? TextInputColumn::make('desember')->disabled(fn ($record) => !static::canEdit($record))->alignRight()->rules(['numeric'])->type('number') : TextColumn::make('desember')
                    ->formatStateUsing(function ($state) {
                        if ($state !== null) {
                            return number_format((float)$state);
                        }
                    })->sortable()->alignRight(),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()->toggleable(),
                Tables\Columns\TextColumn::make('updated_at')
                    ->dateTime()->toggleable(),
            ])
            ->filters([
                //
            ])
            ->recordActions([
                Actions\Action::make('duplikat')->action(function (RkaGaji $record) {
                    abort_unless(static::canEdit($record), 403);
                    $data = RkaGaji::find($record->id);
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
            'index' => Pages\ManageRkaGajis::route('/'),
        ];
    }
}
