<?php

namespace App\Filament\Resources\RkaOperasionalResource\Widgets;

use App\Filament\Widgets\LegacyBudgetOverview;
use App\Models\RkaOperasional;

class TotalOperasionalOverview extends LegacyBudgetOverview
{
    protected static string $model = RkaOperasional::class;
}
