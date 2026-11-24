<?php

namespace App\Http\Controllers\Api;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Resources\AvailableSlotsResource;
use App\Services\AppointmentAvailabilityService;
use Illuminate\Http\Request;


class AvailabilityController extends Controller
{
    public function __invoke(
        Request $request,
        AppointmentAvailabilityService $service
    ): AvailableSlotsResource {
        $data = $request->validate([
            'doctor_id' => [
                'required',
                'integer',
                'exists:doctors,id',
            ],
            'branch_id' => [
                'required',
                'integer',
                'exists:branches,id',
            ],
            'date' => [
                'required',
                'date_format:Y-m-d',
            ],
        ]);

        $user = $request->user();

        if (
            $user->role === UserRole::RECEPTIONIST
            && (int) $user->branch_id !== (int) $data['branch_id']
        ) {
            abort(403, 'You cannot access another branch.');
        }

        $slots = $service->getAvailableSlots(
            (int) $data['doctor_id'],
            (int) $data['branch_id'],
            $data['date']
        );

        return new AvailableSlotsResource([
            'doctor_id' => (int) $data['doctor_id'],
            'branch_id' => (int) $data['branch_id'],
            'date' => $data['date'],
            'slots' => $slots,
        ]);
    }
}
