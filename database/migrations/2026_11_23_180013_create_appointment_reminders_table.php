<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('appointment_reminders', function (Blueprint $table) {
            $table->id();

            $table->foreignId('appointment_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->date('reminder_date');

            $table->string('status')->default('pending');

            $table->timestamp('sent_at')->nullable();

            $table->timestamps();

            $table->unique([
                'appointment_id',
                'reminder_date',
            ]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('appointment_reminders');
    }
};
