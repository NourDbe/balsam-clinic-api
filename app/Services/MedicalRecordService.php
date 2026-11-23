<?php

namespace App\Services;

use App\Enums\AppointmentStatus;
use App\Models\Appointment;
use App\Models\MedicalRecord;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class MedicalRecordService
{
    public function create(
        Appointment $appointment,
        array $data
    ): MedicalRecord {
        return DB::transaction(function () use ($appointment, $data) {
            $appointment = Appointment::query()
                ->lockForUpdate()
                ->findOrFail($appointment->id);

            if ($appointment->status !== AppointmentStatus::ARRIVED) {
                throw ValidationException::withMessages([
                    'appointment' => 'يجب تسجيل وصول المريض أولاً.',
                ]);
            }

            if ($appointment->medicalRecord()->exists()) {
                throw ValidationException::withMessages([
                    'appointment' => 'يوجد سجل طبي لهذا الموعد.',
                ]);
            }

            return $appointment->medicalRecord()->create($data);
        }, 3);
    }

    public function update(
        MedicalRecord $medicalRecord,
        array $data
    ): MedicalRecord {
        return DB::transaction(function () use ($medicalRecord, $data) {
            $record = MedicalRecord::query()
                ->lockForUpdate()
                ->findOrFail($medicalRecord->id);

            $record->update($data);

            return $record->refresh();
        }, 3);
    }
}
