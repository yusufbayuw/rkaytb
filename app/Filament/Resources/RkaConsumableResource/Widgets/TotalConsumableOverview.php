<?php

namespace App\Filament\Resources\RkaConsumableResource\Widgets;

use App\Filament\Widgets\LegacyBudgetOverview;
use App\Models\RkaConsumable;

class TotalConsumableOverview extends LegacyBudgetOverview
{
    protected static string $model = RkaConsumable::class;
}
