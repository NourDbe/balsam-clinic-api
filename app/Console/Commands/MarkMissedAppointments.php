<?php

namespace App\Console\Commands;

use App\Models\Branch;
use App\Services\MissedAppointmentService;
use Carbon\CarbonImmutable;
use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

class MarkMissedAppointments extends Command
{
    protected $signature = 'clinic:mark-missed
        {--as-of= : Syria local datetime YYYY-MM-DD HH:MM}
        {--branch= : Filter by branch ID}
        {--grace=15 : Grace period in minutes}
        {--dry-run : Preview without updating}
        {--fail : Simulate a command failure}';

    protected $description =
        'Mark missed appointments and ban repeat no-shows';

    public function handle(
        MissedAppointmentService $service
    ): int {
        $inputDate = $this->option('as-of');

        if ($inputDate !== null) {
            $parsed = DateTimeImmutable::createFromFormat(
                '!Y-m-d H:i',
                $inputDate,
                new DateTimeZone('Asia/Damascus')
            );

            if (
                ! $parsed ||
                $parsed->format('Y-m-d H:i') !== $inputDate
            ) {
                $this->error('Invalid --as-of format.');
                return self::INVALID;
            }

            $asOf = CarbonImmutable::instance($parsed);
        } else {
            $asOf = CarbonImmutable::now('Asia/Damascus');
        }

        $graceOption = (string) $this->option('grace');

        if (
            ! ctype_digit($graceOption) ||
            (int) $graceOption > 120
        ) {
            $this->error('Grace must be between 0 and 120.');
            return self::INVALID;
        }

        $branchId = null;
        $branchOption = $this->option('branch');

        if ($branchOption !== null) {
            if (
                ! ctype_digit((string) $branchOption) ||
                (int) $branchOption < 1 ||
                ! Branch::whereKey($branchOption)->exists()
            ) {
                $this->error('Invalid branch ID.');
                return self::INVALID;
            }

            $branchId = (int) $branchOption;
        }

        try {
            if (
                $this->option('fail') ||
                config('clinic.simulate_mark_missed_failure', false)
            ) {
                throw new RuntimeException(
                    'Simulated missed appointments failure.'
                );
            }

            $result = $service->run(
                $asOf,
                $branchId,
                (int) $graceOption,
                (bool) $this->option('dry-run')
            );

            $this->info(
                'Checked at: ' .
                $asOf->format('Y-m-d H:i T')
            );

            $this->table(
                ['Eligible', 'Marked', 'Banned', 'Preview', 'Skipped'],
                [[
                    $result['eligible'],
                    $result['marked'],
                    $result['banned'],
                    $result['would_mark'],
                    $result['skipped'],
                ]]
            );

            return self::SUCCESS;

        } catch (Throwable $exception) {
            Log::error('Mark missed command failed', [
                'error' => $exception->getMessage(),
            ]);

            $this->error($exception->getMessage());

            return self::FAILURE;
        }
    }
}
