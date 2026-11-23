<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\Appointment;
use App\Models\User;

class AppointmentPolicy
{
    public function arrive(
        User $user,
        Appointment $appointment
    ): bool {
        return match ($user->role) {
            UserRole::ADMIN => true,

            UserRole::RECEPTIONIST =>
                $user->branch_id !== null
                && $user->branch_id === $appointment->branch_id,

            default => false,
        };
    }

    public function cancel(
        User $user,
        Appointment $appointment
    ): bool {
        return match ($user->role) {
            UserRole::ADMIN => true,

            UserRole::RECEPTIONIST =>
                $user->branch_id !== null
                && $user->branch_id === $appointment->branch_id,

            UserRole::PATIENT =>
                $appointment->patient()
                    ->where('user_id', $user->id)
                    ->exists(),

            default => false,
        };
    }
}
