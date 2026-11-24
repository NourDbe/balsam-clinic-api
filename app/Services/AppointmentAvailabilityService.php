<?php

namespace App\Services;

use App\Enums\AppointmentStatus;
use App\Models\Appointment;
use App\Models\DoctorSchedule;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;

class AppointmentAvailabilityService
{
    public function getAvailableSlots(
        int $doctorId,
        int $branchId,
        string $date
    ): array {
        $key = sprintf(
            'availability:doctor:%s:branch:%s:date:%s',
            $doctorId,
            $branchId,
            $date
        );

        $slots = Cache::remember(
            $key,
            now()->addMinutes(10),
            function () use ($doctorId, $branchId, $date) {
                return $this->calculateSlots(
                    $doctorId,
                    $branchId,
                    $date
                );
            }
        );

        // Never display past time slots.
        return array_values(array_filter(
            $slots,
            fn ($time) => CarbonImmutable::parse(
                "{$date} {$time}",
                'Asia/Damascus'
            )->isFuture()
        ));
    }

    private function calculateSlots(
        int $doctorId,
        int $branchId,
        string $date
    ): array {
        $day = CarbonImmutable::parse(
            $date,
            'Asia/Damascus'
        );

        $schedules = DoctorSchedule::query()
            ->where('doctor_id', $doctorId)
            ->where('branch_id', $branchId)
            ->where('day_of_week', strtolower($day->format('l')))
            ->get();

        $dayStartUtc = $day->startOfDay()->utc();
        $dayEndUtc = $day->addDay()->startOfDay()->utc();

        $appointments = Appointment::query()
            ->where('doctor_id', $doctorId)
            ->whereNotIn('status', [
                AppointmentStatus::CANCELLED->value,
                AppointmentStatus::NO_SHOW->value,
            ])
            ->where('starts_at', '<', $dayEndUtc)
            ->where('ends_at', '>', $dayStartUtc)
            ->get(['starts_at', 'ends_at']);

        $available = [];

        foreach ($schedules as $schedule) {
            $start = CarbonImmutable::parse(
                "{$date} {$schedule->start_time}",
                'Asia/Damascus'
            );

            $end = CarbonImmutable::parse(
                "{$date} {$schedule->end_time}",
                'Asia/Damascus'
            );

            // This version supports same-day shifts.
            if ($end->lessThanOrEqualTo($start)) {
                continue;
            }

            for (
                $slot = $start;
                $slot->addMinutes(30)->lessThanOrEqualTo($end);
                $slot = $slot->addMinutes(30)
            ) {
                $slotUtc = $slot->utc();
                $slotEndUtc = $slotUtc->addMinutes(30);

                $conflict = $appointments->contains(
                    function ($appointment) use (
                        $slotUtc,
                        $slotEndUtc
                    ) {
                        $bookedStart = CarbonImmutable::parse(
                            $appointment->getRawOriginal('starts_at'),
                            'UTC'
                        );

                        $bookedEnd = CarbonImmutable::parse(
                            $appointment->getRawOriginal('ends_at'),
                            'UTC'
                        );

                        return $bookedStart->lessThan($slotEndUtc)
                            && $bookedEnd->greaterThan($slotUtc);
                    }
                );

                if (! $conflict) {
                    $available[$slot->format('H:i')] =
                        $slot->format('H:i');
                }
            }
        }

        ksort($available);

        return array_values($available);
    }
}
