<?php

namespace App\Providers;

use App\Models\Appointment;
use App\Models\MedicalRecord;
use App\Models\Patient;
use App\Observers\AppointmentObserver;
use App\Observers\MedicalRecordObserver;
use App\Observers\PatientObserver;
use Illuminate\Support\ServiceProvider;
use App\Contracts\SmsSender;
use App\Services\LogSmsSender;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
{
    $this->app->bind(
        SmsSender::class,
        LogSmsSender::class
    );
}

    public function boot(): void
    {
        Appointment::observe(AppointmentObserver::class);

        MedicalRecord::observe(MedicalRecordObserver::class);

        Patient::observe(PatientObserver::class);
    }
}
