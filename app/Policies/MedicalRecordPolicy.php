<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\Appointment;
use App\Models\MedicalRecord;
use App\Models\User;

class MedicalRecordPolicy
{
    public function create(User $user, Appointment $appointment): bool
    {
        return $user->role === UserRole::DOCTOR
            && $appointment->doctor()
                ->where('user_id', $user->id)
                ->exists();
    }

    public function view(User $user, MedicalRecord $record): bool
    {
        if ($user->role === UserRole::ADMIN) {
            return true;
        }

        return $this->isTreatingDoctor($user, $record);
    }

    public function update(User $user, MedicalRecord $record): bool
    {
        return $this->isTreatingDoctor($user, $record);
    }

    private function isTreatingDoctor(
        User $user,
        MedicalRecord $record
    ): bool {
        return $user->role === UserRole::DOCTOR
            && $record->appointment()
                ->whereHas('doctor', fn ($query) =>
                    $query->where('user_id', $user->id)
                )->exists();
    }
}
