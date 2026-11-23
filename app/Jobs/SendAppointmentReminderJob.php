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
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class SendAppointmentReminderJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $timeout = 30;

    public function __construct(
        public int $reminderId
    ) {}

    public function backoff(): array
    {
        return [10, 30, 60];
    }

    public function handle(SmsSender $sms): void
    {
        $claimed = DB::table('appointment_reminders')
            ->where('id', $this->reminderId)
            ->where('status', 'pending')
            ->update([
                'status' => 'processing',
                'updated_at' => now(),
            ]);

        if (! $claimed) {
            return;
        }

        try {
            $reminder = DB::table('appointment_reminders')
                ->find($this->reminderId);

            $appointment = Appointment::with('patient')
                ->find($reminder->appointment_id);

            if (! $appointment ||
                $appointment->status !== AppointmentStatus::BOOKED) {

                DB::table('appointment_reminders')
                    ->where('id', $this->reminderId)
                    ->update([
                        'status' => 'skipped',
                        'updated_at' => now(),
                    ]);

                return;
            }

            $date = $appointment->starts_at
                ->copy()
                ->timezone('Asia/Damascus')
                ->format('Y-m-d H:i');

            $sms->send(
                $appointment->patient->phone,
                "تذكير بموعدك بتاريخ {$date}."
            );

            DB::table('appointment_reminders')
                ->where('id', $this->reminderId)
                ->update([
                    'status' => 'sent',
                    'sent_at' => now(),
                    'updated_at' => now(),
                ]);

        } catch (Throwable $exception) {

            DB::table('appointment_reminders')
                ->where('id', $this->reminderId)
                ->update([
                    'status' => 'pending',
                    'updated_at' => now(),
                ]);

            throw $exception;
        }
    }

    public function failed(Throwable $exception): void
    {
        DB::table('appointment_reminders')
            ->where('id', $this->reminderId)
            ->update([
                'status' => 'failed',
                'updated_at' => now(),
            ]);

        Log::error('Appointment reminder failed', [
            'reminder_id' => $this->reminderId,
            'error' => $exception->getMessage(),
        ]);
    }
}
