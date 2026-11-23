<?php

namespace App\Listeners;

use App\Events\AppointmentBooked;
use App\Jobs\SendBookingConfirmationJob;

class QueueBookingConfirmation
{
    public function handle(AppointmentBooked $event): void
    {
        SendBookingConfirmationJob::dispatch(
            $event->appointmentId
        )->onQueue('high')->afterCommit();
    }
}
