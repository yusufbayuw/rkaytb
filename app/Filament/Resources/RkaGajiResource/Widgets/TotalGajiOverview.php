<?php

namespace App\Filament\Resources\RkaGajiResource\Widgets;

use App\Filament\Widgets\LegacyBudgetOverview;
use App\Models\RkaGaji;

class TotalGajiOverview extends LegacyBudgetOverview
{
    protected static string $model = RkaGaji::class;
}
