<?php

namespace App\Filament\Resources\RkaResource\Pages;

use App\Filament\Resources\RkaResource;
use App\Models\RkaCategory;
use App\Support\RkaEntryAccess;
use Filament\Actions;
use Filament\Resources\Pages\ManageRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

class ManageRkas extends ManageRecords
{
    use \Filament\Pages\Concerns\ExposesTableToWidgets;
    protected static string $resource = RkaResource::class;

    public function getTabs(): array
    {
        $tabs = ['all' => Tab::make('Semua')];
        foreach (RkaCategory::orderBy('sort_order')->orderBy('id')->get() as $category) {
            $tabs[(string) $category->id] = Tab::make($category->name.($category->is_active ? '' : ' (nonaktif)'))
                ->modifyQueryUsing(fn (Builder $query) => $query->where('category_id', $category->id));
        }
        return $tabs;
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('toggleEditMode')->label(fn () => auth()->user()->is_editmode ? 'Mode: Edit' : 'Mode: Normal')
                ->visible(fn () => RkaEntryAccess::canWrite())->action(function (): void {
                    abort_unless(RkaEntryAccess::canWrite(), 403);
                    $user = auth()->user(); $user->is_editmode = !$user->is_editmode; $user->save();
                    $this->resetTable();
                }),
            Actions\CreateAction::make()->label('Tambah RKA')->databaseTransaction()
                ->visible(fn () => RkaEntryAccess::canWrite() && (!is_numeric($this->activeTab)
                    || (bool) RkaCategory::find($this->activeTab)?->is_active)),
        ];
    }

    protected function getHeaderWidgets(): array { return [\App\Filament\Widgets\RkaOverview::class]; }
}
