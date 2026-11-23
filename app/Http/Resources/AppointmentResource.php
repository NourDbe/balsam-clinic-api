<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AppointmentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'reference_number' => $this->reference_number,

            'patient' => [
                'id' => $this->patient_id,
                'name' => $this->whenLoaded(
                    'patient',
                    fn () => $this->patient->full_name
                ),
            ],

            'doctor' => [
                'id' => $this->doctor_id,
                'name' => $this->whenLoaded(
                    'doctor',
                    fn () => $this->doctor->full_name
                ),
            ],

            'branch_id' => $this->branch_id,

            'starts_at' => $this->starts_at
                ->toIso8601String(),

            'ends_at' => $this->ends_at
                ->toIso8601String(),

            'status' => $this->status->value,
            'created_at' => $this->created_at
                ->toIso8601String(),
        ];
    }
}
