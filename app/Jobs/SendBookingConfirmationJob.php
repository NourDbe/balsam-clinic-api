<?php

namespace App\Jobs;

use App\Contracts\SmsSender;
use App\Enums\AppointmentStatus;
use App\Models\Appointment;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

class SendBookingConfirmationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $timeout = 30;

    public function __construct(
        public int $appointmentId
    ) {}

    public function backoff(): array
    {
        return [10, 30, 60];
    }

    public function handle(SmsSender $sms): void
    {
        $appointment = Appointment::with('patient')
            ->find($this->appointmentId);

        if (! $appointment ||
            $appointment->status === AppointmentStatus::CANCELLED) {
            return;
        }

        $message = 'تم تأكيد حجز موعدك. الرقم المرجعي: '
            . $appointment->reference_number;

        $sms->send(
            $appointment->patient->phone,
            $message
        );
    }

    public function failed(Throwable $exception): void
    {
        Log::error('Booking confirmation job failed', [
            'appointment_id' => $this->appointmentId,
            'error' => $exception->getMessage(),
        ]);
    }
}
