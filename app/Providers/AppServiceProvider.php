<?php

namespace App\Providers;

use App\Models\Appointment;
use App\Models\MedicalRecord;
use App\Models\Patient;
use App\Observers\AppointmentObserver;
use App\Observers\MedicalRecordObserver;
use App\Observers\PatientObserver;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        Appointment::observe(AppointmentObserver::class);

        MedicalRecord::observe(MedicalRecordObserver::class);

        Patient::observe(PatientObserver::class);
    }
}
