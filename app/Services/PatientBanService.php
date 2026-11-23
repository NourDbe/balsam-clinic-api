<?php

namespace App\Services;

use App\Models\Patient;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

class PatientBanService
{
    public function liftExpired(
        CarbonImmutable $asOf,
        bool $dryRun = false
    ): array {
        $result = [
            'eligible' => 0,
            'lifted' => 0,
            'would_lift' => 0,
            'skipped' => 0,
        ];

        $cutoff = $asOf->utc()->toDateTimeString();

        Patient::query()
            ->whereNotNull('online_booking_banned_until')
            ->where('online_booking_banned_until', '<=', $cutoff)
            ->chunkById(100, function ($patients) use (
                $asOf,
                $dryRun,
                &$result
            ) {
                foreach ($patients as $candidate) {
                    $result['eligible']++;

                    if ($dryRun) {
                        $result['would_lift']++;
                        continue;
                    }

                    $lifted = DB::transaction(
                        function () use ($candidate, $asOf) {
                            $patient = Patient::query()
                                ->whereKey($candidate->id)
                                ->lockForUpdate()
                                ->first();

                            if (
                                ! $patient ||
                                $patient->online_booking_banned_until === null ||
                                $patient->online_booking_banned_until->gt($asOf)
                            ) {
                                return false;
                            }

                            $patient->online_booking_banned_until = null;

                            // Triggers PatientObserver and Audit Log.
                            $patient->save();

                            return true;
                        },
                        3
                    );

                    if ($lifted) {
                        $result['lifted']++;
                    } else {
                        $result['skipped']++;
                    }
                }
            });

        return $result;
    }
}
