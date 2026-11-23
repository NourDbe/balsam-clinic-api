<?php

namespace App\Services;

use App\Enums\AppointmentStatus;
use App\Models\Branch;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class MonthlyReportService
{
    public function generate(string $month, ?int $branchId = null, bool $dryRun = false): array
    {
        $from = CarbonImmutable::parse($month . '-01 00:00:00', 'Asia/Damascus');
        $startUtc = $from->utc()->toDateTimeString();
        $endUtc = $from->addMonth()->utc()->toDateTimeString();

        $results = [];

        $branches = Branch::query()
            ->when($branchId !== null, fn ($query) => $query->whereKey($branchId))
            ->orderBy('id')
            ->get(['id', 'name', 'code']);

        foreach ($branches as $branch) {
            $base = DB::table('appointments')
                ->where('appointments.branch_id', $branch->id)
                ->where('appointments.starts_at', '>=', $startUtc)
                ->where('appointments.starts_at', '<', $endUtc);

            $summary = (clone $base)
                ->selectRaw(
                    'COUNT(*) as total, COALESCE(SUM(CASE WHEN status = ? THEN 1 ELSE 0 END), 0) as no_shows',
                    [AppointmentStatus::NO_SHOW->value]
                )
                ->first();

            $doctors = (clone $base)
                ->join('doctors', 'doctors.id', '=', 'appointments.doctor_id')
                ->select('doctors.id', 'doctors.full_name')
                ->selectRaw('COUNT(*) as appointment_count')
                ->groupBy('doctors.id', 'doctors.full_name')
                ->orderByDesc('appointment_count')
                ->orderBy('doctors.id')
                ->get();

            $topDoctor = $doctors->first();
            $relativePath = "reports/{$month}/branch-{$branch->id}.csv";

            if (! $dryRun) {
                $stream = fopen('php://temp', 'w+');

                if ($stream === false) {
                    throw new RuntimeException('Could not create CSV stream.');
                }

                try {
                    // UTF-8 BOM makes Arabic names easier to read in Excel.
                    fwrite($stream, "\xEF\xBB\xBF");
                    fputcsv($stream, [
                        'month', 'branch_id', 'branch_name',
                        'total_appointments', 'no_shows',
                        'busiest_doctor', 'busiest_doctor_count',
                        'doctor_id', 'doctor_name', 'doctor_appointments',
                    ], ',', '"', '');

                    // Even branches with no appointments get a report row.
                    $rows = $doctors->isEmpty() ? [null] : $doctors;

                    foreach ($rows as $doctor) {
                        fputcsv($stream, [
                            $month,
                            $branch->id,
                            $this->safeCsvCell($branch->name),
                            (int) $summary->total,
                            (int) $summary->no_shows,
                            $this->safeCsvCell($topDoctor?->full_name ?? ''),
                            (int) ($topDoctor?->appointment_count ?? 0),
                            $doctor?->id ?? '',
                            $this->safeCsvCell($doctor?->full_name ?? ''),
                            (int) ($doctor?->appointment_count ?? 0),
                        ], ',', '"', '');
                    }

                    rewind($stream);
                    $csv = stream_get_contents($stream);

                    if ($csv === false || ! Storage::disk('local')->put($relativePath, $csv)) {
                        throw new RuntimeException("Failed to write report: {$relativePath}");
                    }
                } finally {
                    fclose($stream);
                }
            }

            $results[] = [
                'branch' => $branch->name,
                'total' => (int) $summary->total,
                'no_shows' => (int) $summary->no_shows,
                'busiest' => $topDoctor?->full_name ?? '—',
                'path' => $relativePath,
                'saved' => ! $dryRun,
            ];
        }

        return $results;
    }

    private function safeCsvCell(string $value): string
    {
        // Prevent spreadsheet formulas in user-provided names.
        return preg_match('/^\s*[=+\-@]/u', $value) ? "'" . $value : $value;
    }
}
