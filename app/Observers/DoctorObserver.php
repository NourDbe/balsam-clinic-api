<?php

namespace App\Observers;

use App\Models\Doctor;
use App\Services\DoctorDirectoryService;
use Illuminate\Contracts\Events\ShouldHandleEventsAfterCommit;

class DoctorObserver implements ShouldHandleEventsAfterCommit
{
    public function updated(Doctor $doctor): void
    {
        if (! $doctor->wasChanged([
            'full_name',
            'specialty',
        ])) {
            return;
        }

        $service = app(DoctorDirectoryService::class);

        $branchIds = $doctor->schedules()
            ->distinct()
            ->pluck('branch_id');

        foreach ($branchIds as $branchId) {
            $service->clearBranch((int) $branchId);
        }
    }
}
