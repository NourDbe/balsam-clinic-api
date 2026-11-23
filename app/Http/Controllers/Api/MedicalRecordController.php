<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreMedicalRecordRequest;
use App\Http\Requests\UpdateMedicalRecordRequest;
use App\Http\Resources\AuditLogResource;
use App\Http\Resources\MedicalRecordResource;
use App\Models\Appointment;
use App\Models\MedicalRecord;
use App\Services\MedicalRecordService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

class MedicalRecordController extends Controller
{
    public function show(
        Appointment $appointment
    ): MedicalRecordResource {
        $record = $appointment->medicalRecord()
            ->firstOrFail();

        Gate::authorize('view', $record);

        return new MedicalRecordResource($record);
    }

    public function store(
        StoreMedicalRecordRequest $request,
        Appointment $appointment,
        MedicalRecordService $service
    ): JsonResponse {
        $record = $service->create(
            $appointment,
            $request->validated()
        );

        return (new MedicalRecordResource($record))
            ->response()
            ->setStatusCode(201);
    }

    public function update(
        UpdateMedicalRecordRequest $request,
        MedicalRecord $medicalRecord,
        MedicalRecordService $service
    ): MedicalRecordResource {
        return new MedicalRecordResource(
            $service->update(
                $medicalRecord,
                $request->validated()
            )
        );
    }

    public function auditLogs(
        MedicalRecord $medicalRecord
    ): AnonymousResourceCollection {
        Gate::authorize('view', $medicalRecord);

        return AuditLogResource::collection(
            $medicalRecord->auditLogs()
                ->orderByDesc('id')
                ->paginate(15)
        );
    }
}
