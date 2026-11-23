<?php

namespace App\Services;

use App\Enums\AppointmentStatus;
use App\Models\Appointment;
use App\Models\Patient;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

class MissedAppointmentService
{
    public function run(
        CarbonImmutable $asOf,
        ?int $branchId = null,
        int $graceMinutes = 15,
        bool $dryRun = false
    ): array {
        $cutoff = $asOf
            ->subMinutes($graceMinutes)
            ->utc();

        $result = [
            'eligible' => 0,
            'marked' => 0,
            'banned' => 0,
            'would_mark' => 0,
            'skipped' => 0,
        ];

        Appointment::query()
            ->where('status', AppointmentStatus::BOOKED->value)
            ->whereNull('arrived_at')
            ->where('ends_at', '<=', $cutoff)
            ->when($branchId, fn ($query) =>
                $query->where('branch_id', $branchId)
            )
            ->chunkById(100, function ($appointments) use (
                $asOf,
                $cutoff,
                $dryRun,
                &$result
            ) {
                foreach ($appointments as $candidate) {
                    $result['eligible']++;

                    if ($dryRun) {
                        $result['would_mark']++;
                        continue;
                    }

                    $outcome = DB::transaction(
                        function () use (
                            $candidate,
                            $asOf,
                            $cutoff
                        ) {
                            // Lock patient before changing attendance.
                            $patient = Patient::query()
                                ->whereKey($candidate->patient_id)
                                ->lockForUpdate()
                                ->firstOrFail();

                            $appointment = Appointment::query()
                                ->whereKey($candidate->id)
                                ->lockForUpdate()
                                ->first();

                            if (
                                ! $appointment ||
                                $appointment->status !==
                                    AppointmentStatus::BOOKED ||
                                $appointment->arrived_at !== null ||
                                $appointment->ends_at->gt($cutoff)
                            ) {
                                return 'skipped';
                            }

                            // Model save triggers AppointmentObserver.
                            $appointment->status =
                                AppointmentStatus::NO_SHOW;

                            $appointment->save();

                            // Count absences in a rolling 30-day window.
                            $windowStart = $asOf
                                ->subDays(30)
                                ->utc();

                            $noShows = Appointment::query()
                                ->where('patient_id', $patient->id)
                                ->where(
                                    'status',
                                    AppointmentStatus::NO_SHOW->value
                                )
                                ->where('ends_at', '>=', $windowStart)
                                ->where('ends_at', '<=', $cutoff)
                                ->count();

                            if (
                                $noShows >= 3 &&
                                (
                                    ! $patient->online_booking_banned_until ||
                                    $patient->online_booking_banned_until
                                        ->lte($asOf)
                                )
                            ) {
                                $patient->online_booking_banned_until =
                                    $asOf->addDays(30)->utc();

                                // Triggers PatientObserver and Audit Log.
                                $patient->save();

                                return 'banned';
                            }

                            return 'marked';
                        },
                        3
                    );

                    if ($outcome === 'banned') {
                        $result['banned']++;
                        $result['marked']++;
                    } elseif ($outcome === 'marked') {
                        $result['marked']++;
                    } else {
                        $result['skipped']++;
                    }
                }
            });

        return $result;
    }
}
