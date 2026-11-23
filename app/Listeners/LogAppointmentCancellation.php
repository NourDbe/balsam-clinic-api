<?php

namespace App\Listeners;

use App\Events\AppointmentCancelled;
use Illuminate\Support\Facades\Log;

class LogAppointmentCancellation
{
    public function handle(AppointmentCancelled $event): void
    {
        Log::info('Appointment cancelled', [
            'appointment_id' => $event->appointmentId,
        ]);
    }
}
