<?php

namespace App\Services;

use App\Enums\AppointmentStatus;
use App\Events\AppointmentCancelled;
use App\Models\Appointment;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AppointmentWorkflowService
{
    public function arrive(Appointment $appointment): Appointment
    {
        return DB::transaction(function () use ($appointment) {

            $appointment = Appointment::query()
                ->lockForUpdate()
                ->findOrFail($appointment->id);

            if ($appointment->status !== AppointmentStatus::BOOKED) {
                throw ValidationException::withMessages([
                    'status' => 'لا يمكن تسجيل وصول هذا الموعد.',
                ]);
            }

            $appointment->update([
                'status' => AppointmentStatus::ARRIVED,
                'arrived_at' => now('UTC'),
            ]);

            return $appointment->load([
                'patient',
                'doctor',
                'branch',
            ]);
        }, 3);
    }

    public function cancel(Appointment $appointment): Appointment
    {
        $updated = DB::transaction(function () use ($appointment) {

            $appointment = Appointment::query()
                ->lockForUpdate()
                ->findOrFail($appointment->id);

            if ($appointment->status !== AppointmentStatus::BOOKED) {
                throw ValidationException::withMessages([
                    'status' => 'لا يمكن إلغاء هذا الموعد.',
                ]);
            }

            $appointment->update([
                'status' => AppointmentStatus::CANCELLED,
                'cancelled_at' => now('UTC'),
            ]);

            return $appointment->load([
                'patient',
                'doctor',
                'branch',
            ]);
        }, 3);

        // Business event after successful cancellation
        AppointmentCancelled::dispatch($updated->id);

        return $updated;
    }
}
