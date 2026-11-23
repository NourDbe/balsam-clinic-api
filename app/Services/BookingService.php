<?php

namespace App\Services;

use App\Enums\AppointmentStatus;
use App\Enums\UserRole;
use App\Events\AppointmentBooked;
use App\Models\Appointment;
use App\Models\Doctor;
use App\Models\DoctorSchedule;
use App\Models\Patient;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class BookingService
{
    public function book(array $data, User $actor): Appointment
    {
        $startLocal = Carbon::parse(
            $data['date'] . ' ' . $data['start_time'],
            'Asia/Damascus'
        );

        $endLocal = $startLocal->copy()->addMinutes(30);

        if ($startLocal->isPast()) {
            throw ValidationException::withMessages([
                'date' => 'لا يمكن الحجز في الماضي.',
            ]);
        }

        if ($startLocal->toDateString() !== $endLocal->toDateString()) {
            throw ValidationException::withMessages([
                'start_time' => 'وقت الموعد غير صالح.',
            ]);
        }

        $appointment = DB::transaction(function () use (
            $data,
            $actor,
            $startLocal,
            $endLocal
        ) {
            // Lock doctor to prevent concurrent bookings
            Doctor::query()
                ->whereKey($data['doctor_id'])
                ->lockForUpdate()
                ->firstOrFail();

            $patient = Patient::query()
                ->whereKey($data['patient_id'])
                ->lockForUpdate()
                ->firstOrFail();

            // Check booking ban
            if (
            $patient->online_booking_banned_until?->isFuture()
            && $actor->role === UserRole::PATIENT
        ) {
            throw ValidationException::withMessages([
                'patient_id' => 'المريض ممنوع من الحجز أونلاين.',
            ]);
        }

            // Check doctor's schedule
            $day = strtolower($startLocal->format('l'));

            $availableSchedule = DoctorSchedule::query()
                ->where('doctor_id', $data['doctor_id'])
                ->where('branch_id', $data['branch_id'])
                ->where('day_of_week', $day)
                ->where(
                    'start_time',
                    '<=',
                    $startLocal->format('H:i:s')
                )
                ->where(
                    'end_time',
                    '>=',
                    $endLocal->format('H:i:s')
                )
                ->exists();

            if (! $availableSchedule) {
                throw ValidationException::withMessages([
                    'start_time' =>
                        'الطبيب لا يداوم بهذا الفرع أو الوقت.',
                ]);
            }

            $startUtc = $startLocal->copy()->utc();
            $endUtc = $endLocal->copy()->utc();

            // Prevent overlapping appointments
            $conflict = Appointment::query()
                ->where('doctor_id', $data['doctor_id'])
                ->whereNotIn('status', [
                    AppointmentStatus::CANCELLED->value,
                    AppointmentStatus::NO_SHOW->value,
                ])
                ->where('starts_at', '<', $endUtc)
                ->where('ends_at', '>', $startUtc)
                ->exists();

            if ($conflict) {
                throw ValidationException::withMessages([
                    'start_time' => 'هذا الموعد محجوز مسبقاً.',
                ]);
            }

            // Reference number is created by Observer
            return Appointment::create([
                'patient_id' => $data['patient_id'],
                'doctor_id' => $data['doctor_id'],
                'branch_id' => $data['branch_id'],
                'created_by' => $actor->id,
                'starts_at' => $startUtc,
                'ends_at' => $endUtc,
                'status' => AppointmentStatus::BOOKED,
            ]);
        }, 3);

        // Business event after successful booking
        AppointmentBooked::dispatch($appointment->id);

        return $appointment;
    }
}
