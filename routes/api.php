<?php

use App\Http\Controllers\Api\AppointmentController;
use App\Http\Resources\UserResource;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AppointmentActionsController;
use App\Http\Controllers\Api\MedicalRecordController;

Route::middleware('auth:sanctum')->group(function () {

    Route::get('/me', function (Request $request) {
        return new UserResource($request->user());
    });

    Route::get('/appointments', [
        AppointmentController::class, 'index'
    ]);

    Route::get('/appointments/{appointment}', [
        AppointmentController::class, 'show'
    ])->whereNumber('appointment');

    Route::post('/appointments', [
        AppointmentController::class, 'store'
    ]);
    Route::patch('/appointments/{appointment}/arrive', [
    AppointmentActionsController::class,
    'arrive'
]);

Route::patch('/appointments/{appointment}/cancel', [
    AppointmentActionsController::class,
    'cancel'
]);
Route::get('/appointments/{appointment}/medical-record', [
    MedicalRecordController::class,
    'show',
]);

Route::post('/appointments/{appointment}/medical-record', [
    MedicalRecordController::class,
    'store',
]);

Route::patch('/medical-records/{medicalRecord}', [
    MedicalRecordController::class,
    'update',
]);

Route::get('/medical-records/{medicalRecord}/audit-logs', [
    MedicalRecordController::class,
    'auditLogs',
]);
});
