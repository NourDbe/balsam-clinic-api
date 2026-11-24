<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AvailableSlotsResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'doctor_id' => $this->resource['doctor_id'],
            'branch_id' => $this->resource['branch_id'],
            'date' => $this->resource['date'],
            'timezone' => 'Asia/Damascus',
            'slots' => $this->resource['slots'],
        ];
    }
}
