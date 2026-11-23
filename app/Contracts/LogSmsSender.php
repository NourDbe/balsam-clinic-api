<?php

namespace App\Services;

use App\Contracts\SmsSender;
use Illuminate\Support\Facades\Log;

class LogSmsSender implements SmsSender
{
    public function send(string $phone, string $message): void
    {
        Log::info('SMS DEMO', [
            'phone_last4' => substr($phone, -4),
            'message' => $message,
        ]);
    }
}
