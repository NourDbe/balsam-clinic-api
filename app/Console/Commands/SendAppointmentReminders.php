<?php

namespace App\Console\Commands;

use App\Models\Branch;
use App\Services\AppointmentReminderService;
use DateTimeImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Throwable;

class SendAppointmentReminders extends Command
{
    protected $signature = 'clinic:send-reminders
        {--date= : Appointment date YYYY-MM-DD, defaults to tomorrow}
        {--branch= : Filter by branch ID}
        {--dry-run : Preview without queueing reminders}';

    protected $description =
        'Queue appointment reminders without duplicates';

    public function handle(
        AppointmentReminderService $service
    ): int {
        $date = $this->option('date')
            ?: now('Asia/Damascus')->addDay()->toDateString();

        $parsed = DateTimeImmutable::createFromFormat(
            '!Y-m-d', $date
        );

        if (! $parsed || $parsed->format('Y-m-d') !== $date) {
            $this->error('Invalid date. Use YYYY-MM-DD.');
            return self::INVALID;
        }

        $branchOption = $this->option('branch');
        $branchId = null;

        if ($branchOption !== null) {
            if (
                ! ctype_digit((string) $branchOption)
                || (int) $branchOption < 1
                || ! Branch::whereKey($branchOption)->exists()
            ) {
                $this->error('Invalid branch ID.');
                return self::INVALID;
            }

            $branchId = (int) $branchOption;
        }

        try {
            $result = $service->run(
                $date,
                $branchId,
                (bool) $this->option('dry-run')
            );

            $this->info("Appointment date: {$date}");

            $this->table(
                ['Eligible', 'Queued', 'Existing', 'Dry-run preview'],
                [[
                    $result['eligible'],
                    $result['queued'],
                    $result['already_exists'],
                    $result['would_queue'],
                ]]
            );

            return self::SUCCESS;

        } catch (Throwable $exception) {
            Log::error('Reminder command failed', [
                'error' => $exception->getMessage(),
            ]);

            $this->error($exception->getMessage());

            return self::FAILURE;
        }
    }
}
