<?php

namespace App\Observers;
use Illuminate\Support\Facades\Auth;

use App\Models\Patient;

class PatientObserver
{
    public function updated(Patient $patient): void
    {
        $fields = [
            'full_name',
            'phone',
            'date_of_birth',
            'online_booking_banned_until',
        ];

        $changes = array_intersect_key(
            $patient->getChanges(),
            array_flip($fields)
        );

        if (empty($changes)) {
            return;
        }

        $oldValues = array_intersect_key(
            $patient->getPrevious(),
            $changes
        );

        $patient->auditLogs()->create([
            'user_id' => auth()->guard()->id(),
            'action' => 'updated',
            'old_values' => $oldValues,
            'new_values' => $changes,
        ]);
    }
}
