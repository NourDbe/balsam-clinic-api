<?php

namespace App\Console\Commands;

use App\Models\Branch;
use App\Services\MonthlyReportService;
use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

class GenerateMonthlyReports extends Command
{
    protected $signature = 'clinic:monthly-report
        {--month= : Report month YYYY-MM; defaults to previous month in Syria}
        {--branch= : Generate for one branch ID}
        {--dry-run : Preview results without saving CSV files}
        {--fail : Simulate a command failure}';

    protected $description = 'Generate monthly CSV appointment reports for clinic branches';

    public function handle(MonthlyReportService $service): int
    {
        $month = $this->option('month')
            ?: now('Asia/Damascus')->subMonthNoOverflow()->format('Y-m');

        $parsed = DateTimeImmutable::createFromFormat(
            '!Y-m',
            $month,
            new DateTimeZone('Asia/Damascus')
        );

        if (! $parsed || $parsed->format('Y-m') !== $month) {
            $this->error('Invalid month. Use YYYY-MM (example: 2026-11).');
            return self::INVALID;
        }

        $branchOption = $this->option('branch');
        $branchId = null;

        if ($branchOption !== null) {
            if (
                ! ctype_digit((string) $branchOption)
                || (int) $branchOption < 1
                || ! Branch::whereKey((int) $branchOption)->exists()
            ) {
                $this->error('Invalid branch ID.');
                return self::INVALID;
            }

            $branchId = (int) $branchOption;
        }

        try {
            if ($this->option('fail')) {
                throw new RuntimeException('Simulated monthly report failure.');
            }

            $dryRun = (bool) $this->option('dry-run');
            $results = $service->generate($month, $branchId, $dryRun);

            $this->info('Reporting month: ' . $month);
            $this->table(
                ['Branch', 'Appointments', 'No-shows', 'Busiest doctor', 'CSV path'],
                array_map(fn ($row) => [
                    $row['branch'],
                    $row['total'],
                    $row['no_shows'],
                    $row['busiest'],
                    $row['path'],
                ], $results)
            );

            if ($dryRun) {
                $this->comment('Dry run: no files were saved.');
            } else {
                $this->info('CSV files saved on the local disk.');
                $this->line('Local disk root: ' . Storage::disk('local')->path(''));
            }

            return self::SUCCESS;
        } catch (Throwable $exception) {
            Log::error('Monthly report command failed', [
                'month' => $month,
                'error' => $exception->getMessage(),
            ]);

            $this->error($exception->getMessage());
            return self::FAILURE;
        }
    }
}
