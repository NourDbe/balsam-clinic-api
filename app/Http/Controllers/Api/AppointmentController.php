<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreAppointmentRequest;
use App\Http\Resources\AppointmentResource;
use App\Services\BookingService;
use Illuminate\Http\JsonResponse;

class AppointmentController extends Controller
{
    public function store(
        StoreAppointmentRequest $request,
        BookingService $bookingService
    ): JsonResponse {
        $appointment = $bookingService->book(
            $request->validated(),
            $request->user()
        );

        $appointment->load(['patient', 'doctor', 'branch']);

        return (new AppointmentResource($appointment))
            ->response()
            ->setStatusCode(201);
    }
}
