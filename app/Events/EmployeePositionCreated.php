<?php

namespace App\Events;

use App\Models\Employee;
use App\Models\EmployeeKpiScore;
use Illuminate\Broadcasting\Channel;
use Illuminate\Queue\SerializesModels;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Support\Facades\DB;

class EmployeePositionCreated
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * Create a new event instance.
     */
    public function __construct(Employee $employee)
    {
        $this->employee = $employee;
    }

    public function handle()
    {
        // Peroleh data employee dan position yang baru dibuat
        $employee = $this->employee;
        $position = $this->employee->position_id;

        // Ambil kpi_id dari tabel position
        $positionKpiTargets = DB::table('position_kpi_target')->where('position_id', $position)->get();

        foreach ($positionKpiTargets as $positionKpiTarget) {
            // Ambil kpi_id dari setiap objek
            $kpiId = $positionKpiTarget->kpi_target_id;
            EmployeeKpiScore::create([
                'employee_id' => $employee->id,
                'kpi_target_id' => $kpiId,
            ]);
        }
        //$kpiId = $position->kpi_id;

    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, \Illuminate\Broadcasting\Channel>
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('channel-name'),
        ];
    }
}
