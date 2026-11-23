<?php

namespace App\Http\Controllers\Api;

use App\Enums\AppointmentStatus;
use App\Enums\Specialty;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreAppointmentRequest;
use App\Http\Resources\AppointmentResource;
use App\Models\Appointment;
use App\Services\BookingService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;

class AppointmentController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $filters = $request->validate([
            'branch_id' => ['sometimes', 'integer', 'exists:branches,id'],
            'doctor_id' => ['sometimes', 'integer', 'exists:doctors,id'],
            'date' => ['sometimes', 'date_format:Y-m-d'],
            'status' => [
                'sometimes',
                Rule::enum(AppointmentStatus::class),
            ],
            'specialty' => [
                'sometimes',
                Rule::enum(Specialty::class),
            ],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:50'],
        ]);

        $appointments = Appointment::query()
            ->visibleTo($request->user())
            ->filter($filters)
            ->with(['patient', 'doctor', 'branch'])
            ->orderByDesc('starts_at')
            ->orderByDesc('id')
            ->paginate($filters['per_page'] ?? 15)
            ->withQueryString();

        return AppointmentResource::collection($appointments);
    }

    public function show(
        Request $request,
        int $appointment
    ): AppointmentResource {
        $result = Appointment::query()
            ->visibleTo($request->user())
            ->with(['patient', 'doctor', 'branch'])
            ->findOrFail($appointment);

        return new AppointmentResource($result);
    }

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
