<?php

namespace App\Console\Commands;

use App\Services\PatientBanService;
use Carbon\CarbonImmutable;
use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

class LiftPatientBans extends Command
{
    protected $signature = 'clinic:lift-bans
        {--as-of= : Syria local datetime YYYY-MM-DD HH:MM}
        {--dry-run : Preview without updating patients}
        {--fail : Simulate a command failure}';

    protected $description =
        'Lift expired online booking bans for patients';

    public function handle(PatientBanService $service): int
    {
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

        try {
            if (
                $this->option('fail') ||
                config('clinic.simulate_lift_bans_failure', false)
            ) {
                throw new RuntimeException(
                    'Simulated lift bans failure.'
                );
            }

            $result = $service->liftExpired(
                $asOf,
                (bool) $this->option('dry-run')
            );

            $this->info('Checked at: ' .
                $asOf->format('Y-m-d H:i T'));

            $this->table(
                ['Eligible', 'Lifted', 'Preview', 'Skipped'],
                [[
                    $result['eligible'],
                    $result['lifted'],
                    $result['would_lift'],
                    $result['skipped'],
                ]]
            );

            return self::SUCCESS;

        } catch (Throwable $exception) {
            Log::error('Lift bans command failed', [
                'error' => $exception->getMessage(),
            ]);

            $this->error($exception->getMessage());

            return self::FAILURE;
        }
    }
}
