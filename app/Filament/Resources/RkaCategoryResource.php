<?php

namespace App\Filament\Resources;

use App\Models\RkaCategory;
use App\Support\SystemAccess;
use Filament\Actions;
use Filament\Forms\Components\{TextInput, Select, Toggle};
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\{TextColumn, IconColumn};
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class RkaCategoryResource extends Resource
{
    protected static ?string $model = RkaCategory::class;
    protected static ?string $slug = 'rka-categories';
    protected static ?string $modelLabel = 'Kategori RKA';
    protected static ?string $pluralModelLabel = 'Kategori RKA';
    protected static string|\UnitEnum|null $navigationGroup = 'Master Data';
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-tag';
    public static function canViewAny(): bool { return SystemAccess::allowed(); }
    public static function canCreate(): bool { return SystemAccess::allowed(); }
    public static function canEdit(Model $record): bool { return SystemAccess::allowed(); }
    public static function canDelete(Model $record): bool { return SystemAccess::allowed() && $record->legacy_source === null && !$record->entries()->exists(); }
    public static function canDeleteAny(): bool { return false; }

    public static function form(Schema $schema): Schema
    {
        $immutable = fn (?RkaCategory $record) => $record && ($record->legacy_source !== null || $record->entries()->exists());
        return $schema->components([
            TextInput::make('name')->label('Nama kategori')->required()->maxLength(255),
            TextInput::make('code')->label('Kode kategori')->required()->alphaDash()->maxLength(80)
                ->unique(ignoreRecord: true)->disabled($immutable),
            Select::make('direction')->label('Jenis')->options(['expense' => 'Belanja', 'income' => 'Pendapatan'])
                ->default('expense')->required()->disabled($immutable),
            Select::make('calculation_mode')->label('Perhitungan')->options(['quantity_price' => 'Volume × harga satuan', 'manual' => 'Total manual'])
                ->default('quantity_price')->required()->disabled($immutable),
            TextInput::make('document_prefix')->label('Awalan kode dokumen')->required()->alphaDash()->maxLength(20)
                ->unique(ignoreRecord: true)->disabled($immutable),
            TextInput::make('sort_order')->label('Urutan')->integer()->minValue(0)->default(0)->required(),
            Toggle::make('is_active')->label('Aktif untuk anggaran baru')->default(true),
        ])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table->defaultSort('sort_order')->columns([
            TextColumn::make('name')->label('Kategori')->searchable()->sortable(),
            TextColumn::make('code')->label('Kode'),
            TextColumn::make('direction')->label('Jenis')->formatStateUsing(fn ($state) => $state === 'income' ? 'Pendapatan' : 'Belanja'),
            TextColumn::make('calculation_mode')->label('Perhitungan')->formatStateUsing(fn ($state) => $state === 'manual' ? 'Total manual' : 'Volume × harga satuan'),
            IconColumn::make('is_active')->label('Aktif')->boolean(),
        ])->recordActions([Actions\EditAction::make(), Actions\DeleteAction::make()]);
    }

    public static function getPages(): array { return ['index' => RkaCategoryResource\Pages\ManageRkaCategories::route('/')]; }
}
