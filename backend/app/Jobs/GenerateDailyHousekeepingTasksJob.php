<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

use App\Models\Room;
use App\Models\HousekeepingTask;
use App\Events\HousekeepingTaskCreated;
use Carbon\Carbon;

class GenerateDailyHousekeepingTasksJob implements ShouldQueue
{
    use Queueable;

    public function __construct()
    {
        //
    }

    public function handle(): void
    {
        $rooms = Room::whereIn('housekeeping_status', ['dirty', 'inspecting'])
            ->where('status', '!=', 'occupied')
            ->get();

        $today = Carbon::today()->toDateString();

        foreach ($rooms as $room) {
            $taskExists = HousekeepingTask::where('room_id', $room->id)
                ->whereDate('created_at', $today)
                ->whereNotIn('status', ['completed'])
                ->exists();

            if (!$taskExists) {
                $taskType = $room->housekeeping_status === 'inspecting' ? 'inspection' : 'cleaning';

                $task = HousekeepingTask::create([
                    'hotel_id' => $room->hotel_id,
                    'room_id' => $room->id,
                    'task_type' => $taskType,
                    'status' => 'pending'
                ]);

                event(new HousekeepingTaskCreated($task));
            }
        }
    }
}
