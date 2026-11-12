<?php

namespace App\Filament\Resources\SystemActivityResource\Pages;

class ListActivities extends \MrAdder\FilamentLogger\Resources\ActivityResource\Pages\ListActivities
{
    use \App\Support\RequiresSystemAccess;
}
