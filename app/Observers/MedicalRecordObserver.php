<?php

namespace App\Observers;
use Illuminate\Support\Facades\Auth;

use App\Models\MedicalRecord;

class MedicalRecordObserver
{
    public function updated(MedicalRecord $medicalRecord): void
    {
        $fields = [
            'diagnosis',
            'prescription',
            'notes',
        ];

        $changes = array_intersect_key(
            $medicalRecord->getChanges(),
            array_flip($fields)
        );

        if (empty($changes)) {
            return;
        }

        $oldValues = array_intersect_key(
            $medicalRecord->getPrevious(),
            $changes
        );

        $medicalRecord->auditLogs()->create([
            'user_id' => auth()->guard()->id(),
            'action' => 'updated',
            'old_values' => $oldValues,
            'new_values' => $changes,
        ]);
    }
}
