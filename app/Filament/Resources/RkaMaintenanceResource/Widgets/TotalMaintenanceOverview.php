<?php

namespace App\Filament\Resources\RkaMaintenanceResource\Widgets;

use App\Filament\Widgets\LegacyBudgetOverview;
use App\Models\RkaMaintenance;

class TotalMaintenanceOverview extends LegacyBudgetOverview
{
    protected static string $model = RkaMaintenance::class;
}
