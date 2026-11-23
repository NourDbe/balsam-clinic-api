<?php

namespace App\Observers;

use App\Models\Appointment;
use App\Models\Branch;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class AppointmentObserver
{
    public function creating(Appointment $appointment): void
    {
        $branch = Branch::findOrFail($appointment->branch_id);

        $year = $appointment->starts_at
            ->copy()
            ->timezone('Asia/Damascus')
            ->year;

        $prefix = "{$branch->code}-{$year}-";

        $appointment->reference_number = DB::transaction(
            function () use ($appointment, $year, $prefix) {

                // Include existing manually-created references.
                $lastExisting = DB::table('appointments')
                    ->where('reference_number', 'like', $prefix . '%')
                    ->pluck('reference_number')
                    ->map(fn ($ref) => (int) substr(
                        $ref,
                        strlen($prefix)
                    ))
                    ->max() ?? 0;

                DB::table('reference_counters')->insertOrIgnore([
                    'branch_id' => $appointment->branch_id,
                    'year' => $year,
                    'last_number' => $lastExisting,
                ]);

                $counter = DB::table('reference_counters')
                    ->where('branch_id', $appointment->branch_id)
                    ->where('year', $year)
                    ->lockForUpdate()
                    ->first();

                $next = $counter->last_number + 1;

                DB::table('reference_counters')
                    ->where('id', $counter->id)
                    ->update(['last_number' => $next]);

                return $prefix . str_pad(
                    (string) $next,
                    6,
                    '0',
                    STR_PAD_LEFT
                );
            },
            3
        );
    }

    public function created(Appointment $appointment): void
    {
        $this->clearAvailabilityCache($appointment->getAttributes());
    }

    public function updated(Appointment $appointment): void
    {
        if (! $appointment->wasChanged([
            'doctor_id',
            'branch_id',
            'starts_at',
            'ends_at',
            'status',
        ])) {
            return;
        }

        $previous = array_replace(
            $appointment->getAttributes(),
            $appointment->getPrevious()
        );

        $this->clearAvailabilityCache($previous);
        $this->clearAvailabilityCache($appointment->getAttributes());
    }

    public function deleted(Appointment $appointment): void
    {
        $this->clearAvailabilityCache($appointment->getAttributes());
    }

    private function clearAvailabilityCache(array $attributes): void
    {
        $date = Carbon::parse(
            $attributes['starts_at'],
            'UTC'
        )->timezone('Asia/Damascus')->toDateString();

        $key = sprintf(
            'availability:doctor:%s:branch:%s:date:%s',
            $attributes['doctor_id'],
            $attributes['branch_id'],
            $date
        );

        DB::afterCommit(fn () => Cache::forget($key));
    }
}
