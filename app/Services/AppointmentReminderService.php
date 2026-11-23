<?php

namespace App\Services;

use App\Enums\AppointmentStatus;
use App\Jobs\SendAppointmentReminderJob;
use App\Models\Appointment;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class AppointmentReminderService
{
    public function run(
        string $date,
        ?int $branchId = null,
        bool $dryRun = false
    ): array {
        $start = Carbon::parse(
            $date, 'Asia/Damascus'
        )->startOfDay();

        $startUtc = $start->copy()->utc();
        $endUtc = $start->copy()->addDay()->utc();

        $result = [
            'eligible' => 0,
            'queued' => 0,
            'already_exists' => 0,
            'would_queue' => 0,
        ];

        Appointment::query()
            ->where('status', AppointmentStatus::BOOKED->value)
            ->where('starts_at', '>=', $startUtc)
            ->where('starts_at', '<', $endUtc)
            ->when($branchId, fn ($query) =>
                $query->where('branch_id', $branchId)
            )
            ->chunkById(200, function ($appointments) use (
                $date, $dryRun, &$result
            ) {
                foreach ($appointments as $appointment) {
                    $result['eligible']++;

                    $exists = DB::table('appointment_reminders')
                        ->where('appointment_id', $appointment->id)
                        ->where('reminder_date', $date)
                        ->exists();

                    if ($exists) {
                        $result['already_exists']++;
                        continue;
                    }

                    if ($dryRun) {
                        $result['would_queue']++;
                        continue;
                    }

                    $queued = DB::transaction(function () use (
                        $appointment, $date
                    ) {
                        $inserted = DB::table('appointment_reminders')
                            ->insertOrIgnore([
                                'appointment_id' => $appointment->id,
                                'reminder_date' => $date,
                                'status' => 'pending',
                                'created_at' => now(),
                                'updated_at' => now(),
                            ]);

                        if (! $inserted) {
                            return false;
                        }

                        $reminderId = DB::table('appointment_reminders')
                            ->where('appointment_id', $appointment->id)
                            ->where('reminder_date', $date)
                            ->value('id');

                        SendAppointmentReminderJob::dispatch(
                            (int) $reminderId
                        )->onQueue('low')->afterCommit();

                        return true;
                    });

                    if ($queued) {
                        $result['queued']++;
                    } else {
                        $result['already_exists']++;
                    }
                }
            });

        return $result;
    }
}
