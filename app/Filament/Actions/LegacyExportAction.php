<?php

namespace App\Filament\Actions;

use App\Services\LegacyExportService;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Actions\Action;
use Illuminate\Support\HtmlString;

class LegacyExportAction extends Action
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->label('Ekspor')->icon('heroicon-o-arrow-down-tray')->modalWidth('7xl')
            ->schema([
                TextInput::make('filename')->label('Nama berkas')->default('rka-export')->required()->maxLength(100),
                TextInput::make('prefix')->label('Awalan nama berkas')->maxLength(30),
                Select::make('format')->options(['xlsx' => 'Excel (XLSX)', 'csv' => 'CSV', 'pdf' => 'PDF'])->default('xlsx')->required(),
                Select::make('orientation')->label('Orientasi')->options(['portrait' => 'Potret', 'landscape' => 'Lanskap'])->default('portrait')->required()->live(),
                CheckboxList::make('columns')->label('Kolom')->options(fn ($livewire) => $this->options($livewire))
                    ->default(fn ($livewire) => method_exists($livewire::getResource(), 'defaultExportColumns')
                        ? $livewire::getResource()::defaultExportColumns() : array_keys($this->options($livewire)))->required()->live()->columns(3),
                Repeater::make('extra')->label('Kolom tambahan')->maxItems(10)->schema([
                    TextInput::make('title')->label('Judul')->required()->maxLength(100),
                    TextInput::make('value')->label('Nilai')->maxLength(255),
                ])->defaultItems(0)->columns(2)->live(),
                Toggle::make('preview')->label('Pratinjau dan cetak')->live(),
                Placeholder::make('preview_table')->label('Pratinjau maksimal 20 baris')
                    ->visible(fn (Get $get) => $get('preview'))
                    ->content(function ($livewire, Get $get) {
                        if (!$get('columns')) return '';
                        $service = app(LegacyExportService::class);
                        $data = $service->dataset($livewire::getResource(), $livewire->getFilteredSortedTableQuery(),
                            $this->definitions($livewire), $get('columns'), $get('extra') ?? [], 20);
                        $html = $service->html($data, $get('filename') ?: 'RKA', $get('orientation') ?? 'portrait');
                        return new HtmlString('<iframe title="Pratinjau ekspor" class="w-full h-96" srcdoc="'.e($html).'"></iframe>');
                    }),
            ])
            ->extraModalFooterActions(fn (Action $action) => [
                $action->makeModalSubmitAction('print', arguments: ['print' => true])->label('Cetak seluruh hasil'),
            ])
            ->action(function ($livewire, array $data, array $arguments) {
                if ($arguments['print'] ?? false) {
                    $service = app(LegacyExportService::class);
                    $dataset = $this->dataset($livewire, $data);
                    $livewire->dispatch('legacy-print', html: $service->html($dataset, $data['filename'], $data['orientation']));
                    return null;
                }
                return app(LegacyExportService::class)->download($this->dataset($livewire, $data), $data);
            });
    }

    private function options($livewire): array
    {
        return array_map(fn ($definition) => $definition['label'], $this->definitions($livewire));
    }

    private function definitions($livewire): array
    {
        $definitions = [];
        foreach ($livewire->getTable()->getColumns() as $column) {
            $numeric = in_array($column->getName(), ['volume', 'harga_satuan', 'total', 'monthly_balance', 'januari', 'februari', 'maret', 'april', 'mei', 'juni', 'juli', 'agustus', 'september', 'oktober', 'november', 'desember'], true);
            $definitions[$column->getName()] = [
                'label' => $column->getLabel(),
                'numeric' => $numeric,
                'value' => function ($record) use ($column, $numeric) {
                    $cell = (clone $column)->record($record);
                    $state = $cell->getState();
                    return !$numeric && method_exists($cell, 'formatState') ? $cell->formatState($state) : $state;
                },
            ];
        }
        return $definitions;
    }

    private function dataset($livewire, array $data): array
    {
        return app(LegacyExportService::class)->dataset($livewire::getResource(), $livewire->getFilteredSortedTableQuery(),
            $this->definitions($livewire), $data['columns'], $data['extra'] ?? []);
    }
}
