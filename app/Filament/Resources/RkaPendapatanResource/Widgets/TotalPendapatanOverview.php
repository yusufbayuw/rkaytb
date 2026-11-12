<?php

namespace App\Filament\Resources\RkaPendapatanResource\Widgets;

use App\Filament\Widgets\LegacyBudgetOverview;
use App\Models\RkaPendapatan;

class TotalPendapatanOverview extends LegacyBudgetOverview
{
    protected static string $model = RkaPendapatan::class;
}
