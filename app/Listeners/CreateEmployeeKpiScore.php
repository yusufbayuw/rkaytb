<?php

namespace App\Listeners;

use App\Events\EmployeePositionCreated;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class CreateEmployeeKpiScore
{
    /**
     * Create the event listener.
     */
    public function __construct()
    {
        //
    }

    /**
     * Handle the event.
     */
    public function handle(EmployeePositionCreated $event): void
    {
        $event->handle();
    }
}
