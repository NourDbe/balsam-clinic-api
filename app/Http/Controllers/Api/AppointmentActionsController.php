<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\AppointmentResource;
use App\Models\Appointment;
use App\Services\AppointmentWorkflowService;
use Illuminate\Support\Facades\Gate;

class AppointmentActionsController extends Controller
{
    public function arrive(
        Appointment $appointment,
        AppointmentWorkflowService $service
    ): AppointmentResource {

        Gate::authorize('arrive', $appointment);

        return new AppointmentResource(
            $service->arrive($appointment)
        );
    }

    public function cancel(
        Appointment $appointment,
        AppointmentWorkflowService $service
    ): AppointmentResource {

        Gate::authorize('cancel', $appointment);

        return new AppointmentResource(
            $service->cancel($appointment)
        );
    }
}
