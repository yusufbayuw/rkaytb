<?php

namespace App\Filament\Resources;

use App\Filament\Actions\LegacyExportAction;
use App\Models\{Akun, RkaCategory, RkaEntry, Setting, Unit};
use App\Support\{RkaDecimal, RkaEntryAccess};
use Filament\Actions;
use Filament\Forms\Components\{Select, TextInput, Toggle};
use Filament\Schemas\Components\{Section, Utilities\Get, Utilities\Set};
use Filament\Schemas\Schema;
use Filament\Resources\Resource;
use Filament\Tables\Columns\{TextColumn, TextInputColumn, SelectColumn};
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\{Builder, Model};

class RkaResource extends Resource
{
    protected static ?string $model = RkaEntry::class;
    protected static ?string $slug = 'rka';
    protected static ?string $modelLabel = 'RKA';
    protected static ?string $pluralModelLabel = 'RKA';
    protected static ?string $navigationLabel = 'RKA';
    protected static string|\UnitEnum|null $navigationGroup = 'Rencana Kerja & Anggaran';
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-table-cells';

    public static function canViewAny(): bool { return RkaEntryAccess::canViewAny(); }
    public static function canCreate(): bool { return RkaEntryAccess::canWrite(); }
    public static function canView(Model $record): bool { return $record instanceof RkaEntry && RkaEntryAccess::canView($record); }
    public static function canEdit(Model $record): bool { return static::canView($record) && RkaEntryAccess::canWrite(); }
    public static function canDelete(Model $record): bool { return static::canEdit($record); }
    public static function canDeleteAny(): bool { return RkaEntryAccess::canWrite(); }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();
        $query = auth()->user()?->is_editmode ? $query->isLeaf() : $query->tree()->depthFirst();
        return RkaEntryAccess::scope($query)->with('category');
    }

    private static function mode(Get $get): ?string { return RkaCategory::find($get('category_id'))?->calculation_mode; }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Anggaran')->schema([
                Select::make('category_id')->label('Kategori')->required()->native()->live()
                    ->options(fn (?RkaEntry $record) => RkaCategory::where('is_active', true)
                        ->when($record, fn ($q) => $q->orWhereKey($record->category_id))->orderBy('sort_order')->pluck('name', 'id'))
                    ->default(fn ($livewire) => is_numeric($livewire->activeTab ?? null) ? $livewire->activeTab : null)
                    ->disabled(fn (?RkaEntry $record) => $record !== null)
                    ->afterStateUpdated(function (Set $set): void {
                        foreach (['parent_id', 'volume', 'harga_satuan', 'total'] as $field) $set($field, null);
                    }),
                TextInput::make('uraian')->label('Uraian')->required()->maxLength(255),
                Select::make('unit_nama')->label('Unit')->options(fn () => Unit::orderBy('nama')->pluck('nama', 'id'))
                    ->required()->default(fn () => auth()->user()->unit_id)->live()->dehydrated()
                    ->disabled(fn () => !RkaEntryAccess::superadmin())
                    ->dehydrateStateUsing(fn ($state) => RkaEntryAccess::superadmin() ? $state : auth()->user()->unit_id)
                    ->afterStateUpdated(fn (Set $set) => $set('parent_id', null)),
                Toggle::make('is_parent')->label('Jadikan Anggaran Induk')->live()->default(false),
                Select::make('parent_id')->label('Anggaran ini menginduk ke')->nullable()
                    ->options(fn (Get $get, ?RkaEntry $record) => RkaEntryAccess::scope(RkaEntry::query())
                        ->where('category_id', $get('category_id'))->where('unit_nama', $get('unit_nama'))
                        ->where('is_parent', true)->when($record, fn ($q) => $q->whereKeyNot($record->id))
                        ->orderBy('uraian')->pluck('uraian', 'id')),
                Select::make('nomor_akun')->label('Nomor akun')->options(function (?RkaEntry $record) {
                    $options = Akun::orderBy('nama')->pluck('nama', 'id')->all();
                    if ($record?->nomor_akun !== null && !isset($options[$record->nomor_akun])) $options[$record->nomor_akun] = $record->nomor_akun;
                    return $options;
                })
                    ->visible(fn () => !Setting::flag(2))->disabled(fn () => !RkaEntryAccess::superadmin())
                    ->dehydrated(fn () => RkaEntryAccess::superadmin() && !Setting::flag(2)),
            ])->columns(2)->columnSpanFull(),
            Section::make('Nilai Anggaran')->visible(fn (Get $get) => !$get('is_parent'))->schema([
                TextInput::make('volume')->label('Volume')->type('number')->inputMode('decimal')->rules(['numeric', 'min:0'])->step('0.0001')->live(onBlur: true)
                    ->visible(fn (Get $get) => static::mode($get) === 'quantity_price')->required(),
                Select::make('satuan')->label('Satuan')->options(config('conf_variable.satuan'))
                    ->visible(fn (Get $get) => static::mode($get) === 'quantity_price'),
                TextInput::make('harga_satuan')->label('Harga satuan')->type('number')->inputMode('decimal')->rules(['numeric', 'min:0'])->step('0.0001')->live(onBlur: true)
                    ->visible(fn (Get $get) => static::mode($get) === 'quantity_price')->required(),
                TextInput::make('total')->label('Total manual')->type('number')->inputMode('decimal')->rules(['numeric', 'min:0'])->step('0.01')->required()
                    ->visible(fn (Get $get) => static::mode($get) === 'manual'),
                \Filament\Infolists\Components\TextEntry::make('calculated_total')->label('Total')
                    ->visible(fn (Get $get) => static::mode($get) === 'quantity_price')
                    ->state(function (Get $get): string {
                        try { return RkaDecimal::display(RkaDecimal::round(bcmul(
                            RkaDecimal::input($get('volume'), 'volume', 4), RkaDecimal::input($get('harga_satuan'), 'harga_satuan', 4), 8))); }
                        catch (\Illuminate\Validation\ValidationException) { return '—'; }
                    }),
                Toggle::make('is_detail')->label('Rincian kegiatan')->default(false),
            ])->columns(3)->columnSpanFull(),
            Section::make('Rencana Bulanan')->visible(fn (Get $get) => !$get('is_parent'))->schema(
                array_map(fn ($month) => TextInput::make($month)->label(ucfirst($month))->type('number')->inputMode('decimal')->rules(['numeric', 'min:0'])->step('0.01')->default(0), RkaEntry::MONTHS)
            )->columns(4)->columnSpanFull(),
        ]);
    }

    public static function monthColumn(string $month): TextColumn|TextInputColumn
    {
        return auth()->user()->is_editmode
            ? TextInputColumn::make($month)->label(ucfirst($month))->type('number')->step('0.01')->rules(['nullable', 'numeric', 'min:0'])
                ->disabled(fn (RkaEntry $record) => !static::canEdit($record) || $record->is_parent)
            : TextColumn::make($month)->label(ucfirst($month))->alignRight()->sortable()->formatStateUsing(fn ($state) => RkaDecimal::display($state));
    }

    public static function table(Table $table): Table
    {
        $edit = auth()->user()->is_editmode;
        $units = Unit::pluck('nama', 'id');
        return $table->paginationPageOptions([50, 100, 200, 300, 500])->defaultPaginationPageOption(50)
            ->columns([
                TextColumn::make('category.name')->label('Kategori')->badge(),
                Setting::flag(2)
                    ? TextColumn::make('nomor_akun')->label('Nomor akun')->formatStateUsing(fn (RkaEntry $record) => $record->documentCode())
                    : (RkaEntryAccess::superadmin()
                        ? SelectColumn::make('nomor_akun')->label('Nomor akun')->options(Akun::pluck('nama', 'id'))
                            ->disabled(fn (RkaEntry $record) => !static::canEdit($record))
                        : TextColumn::make('nomor_akun')->label('Nomor akun')),
                $edit ? TextInputColumn::make('uraian')->rules(['required', 'max:255'])->disabled(fn (RkaEntry $record) => !static::canEdit($record))
                    : TextColumn::make('uraian')->searchable()->weight(fn (RkaEntry $record) => $record->is_parent ? 'bold' : null)
                        ->extraAttributes(fn (RkaEntry $record) => ['style' => 'padding-inline-start: '.(min(20, (int) ($record->depth ?? 0)) * 12).'px']),
                TextColumn::make('is_parent')->label('Jenis baris')->formatStateUsing(fn ($state) => $state ? 'Induk' : 'Rincian')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('unit_nama')->label('Unit')->formatStateUsing(fn ($state) => $units[$state] ?? $state),
                $edit ? TextInputColumn::make('volume')->type('number')->step('0.0001')->rules(['required', 'numeric', 'min:0'])
                    ->disabled(fn (RkaEntry $record) => !static::canEdit($record) || $record->manual() || $record->is_parent)
                    : TextColumn::make('volume')->formatStateUsing(fn ($state) => RkaDecimal::display($state, 4)),
                $edit ? SelectColumn::make('satuan')->options(config('conf_variable.satuan'))
                    ->disabled(fn (RkaEntry $record) => !static::canEdit($record) || $record->manual() || $record->is_parent) : TextColumn::make('satuan'),
                $edit ? TextInputColumn::make('harga_satuan')->label('Harga satuan')->type('number')->step('0.0001')->rules(['required', 'numeric', 'min:0'])
                    ->disabled(fn (RkaEntry $record) => !static::canEdit($record) || $record->manual() || $record->is_parent)
                    : TextColumn::make('harga_satuan')->label('Harga satuan')->formatStateUsing(fn ($state) => RkaDecimal::display($state, 4)),
                $edit ? TextInputColumn::make('total')->label('Total')->type('number')->step('0.01')->rules(['required', 'numeric', 'min:0'])
                    ->disabled(fn (RkaEntry $record) => !static::canEdit($record) || !$record->manual() || $record->is_parent)
                    : TextColumn::make('total')->label('Total')->alignRight()->sortable()->formatStateUsing(fn ($state) => RkaDecimal::display($state)),
                TextColumn::make('monthly_balance')->label('Sisa rencana')->state(fn (RkaEntry $record) => $record->monthlyBalance())
                    ->formatStateUsing(fn ($state) => RkaDecimal::display($state))->color(fn ($state) => str_starts_with((string) $state, '-') ? 'danger' : null),
                ...array_map(fn ($month) => static::monthColumn($month), RkaEntry::MONTHS),
                TextColumn::make('origin_source')->label('Sumber historis')->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('origin_id')->label('ID asal')->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('migration_issues')->label('Pemeriksaan data lama')->badge()
                    ->formatStateUsing(fn ($state) => $state ? 'Perlu diperiksa' : '')->color('warning')->toggleable(),
                TextColumn::make('updated_at')->label('Diperbarui')->dateTime()->toggleable(isToggledHiddenByDefault: true),
            ])->filters([
                SelectFilter::make('category_id')->label('Kategori')->options(fn () => RkaCategory::orderBy('sort_order')->pluck('name', 'id')),
                SelectFilter::make('unit_nama')->label('Unit')->options($units)->visible(fn () => RkaEntryAccess::superadmin()),
            ])->recordActions([
                Actions\Action::make('duplikat')->label('Duplikat')->icon('heroicon-o-document-duplicate')
                    ->visible(fn (RkaEntry $record) => static::canEdit($record))
                    ->action(function (RkaEntry $record): void {
                        abort_unless(static::canEdit($record), 403);
                        $copy = $record->replicate(['depth', 'path', 'document_number', 'origin_source', 'origin_id', 'origin_snapshot', 'migration_hash', 'migration_issues']);
                        \MrAdder\FilamentLogger\Support\ReplicationContextStore::remember($copy, $record);
                        $copy->uraian .= ' COPY';
                        $copy->save();
                    }),
                Actions\ViewAction::make(), Actions\EditAction::make(), Actions\DeleteAction::make(),
            ])->headerActions([LegacyExportAction::make('export')])
            ->toolbarActions([Actions\DeleteBulkAction::make()->authorizeIndividualRecords()->databaseTransaction()]);
    }

    public static function getPages(): array { return ['index' => RkaResource\Pages\ManageRkas::route('/')]; }
    public static function defaultExportColumns(): array
    {
        return ['category.name', 'nomor_akun', 'uraian', 'is_parent', 'unit_nama', 'volume', 'satuan', 'harga_satuan', 'total', ...RkaEntry::MONTHS];
    }
}
