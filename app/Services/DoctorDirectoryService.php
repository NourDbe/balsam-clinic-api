<?php

namespace App\Services;

use App\Enums\Specialty;
use App\Models\Doctor;
use Illuminate\Support\Facades\Cache;

class DoctorDirectoryService
{
    public function list(
        int $branchId,
        ?Specialty $specialty = null
    ): array {
        return Cache::remember(
            $this->key($branchId, $specialty),
            now()->addMinutes(10),
            function () use ($branchId, $specialty) {
                return Doctor::query()
                    ->whereHas('schedules', function ($query) use ($branchId) {
                        $query->where('branch_id', $branchId);
                    })
                    ->when($specialty, function ($query) use ($specialty) {
                        $query->where('specialty', $specialty->value);
                    })
                    ->orderBy('full_name')
                    ->get()
                    ->map(fn (Doctor $doctor) => [
                        'id' => $doctor->id,
                        'full_name' => $doctor->full_name,
                        'specialty' => $doctor->specialty->value,
                    ])
                    ->all();
            }
        );
    }

    public function clearBranch(int $branchId): void
    {
        Cache::forget($this->key($branchId, null));

        foreach (Specialty::cases() as $specialty) {
            Cache::forget($this->key($branchId, $specialty));
        }
    }

    private function key(
        int $branchId,
        ?Specialty $specialty
    ): string {
        return sprintf(
            'doctors:branch:%d:specialty:%s',
            $branchId,
            $specialty?->value ?? 'all'
        );
    }
}
