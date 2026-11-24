<?php

namespace App\Observers;

use App\Models\DoctorSchedule;
use App\Services\DoctorDirectoryService;
use Illuminate\Contracts\Events\ShouldHandleEventsAfterCommit;

class DoctorScheduleObserver implements ShouldHandleEventsAfterCommit
{
    public function created(DoctorSchedule $schedule): void
    {
        $this->clear($schedule);
    }

    public function updated(DoctorSchedule $schedule): void
    {
        $oldBranchId = $schedule->getPrevious()['branch_id']
            ?? $schedule->branch_id;

        $service = app(DoctorDirectoryService::class);

        $service->clearBranch((int) $oldBranchId);
        $service->clearBranch((int) $schedule->branch_id);
    }

    public function deleted(DoctorSchedule $schedule): void
    {
        $this->clear($schedule);
    }

    private function clear(DoctorSchedule $schedule): void
    {
        app(DoctorDirectoryService::class)
            ->clearBranch((int) $schedule->branch_id);
    }
}
