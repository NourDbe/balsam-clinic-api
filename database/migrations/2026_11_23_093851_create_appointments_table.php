<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('appointments', function (Blueprint $table) {
            $table->id();

            $table->string('reference_number')->unique();

            $table->foreignId('patient_id')
                ->constrained()
                ->restrictOnDelete();

            $table->foreignId('doctor_id')
                ->constrained()
                ->restrictOnDelete();

            $table->foreignId('branch_id')
                ->constrained()
                ->restrictOnDelete();

            $table->foreignId('created_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->dateTime('starts_at');
            $table->dateTime('ends_at');

            $table->string('status')->default('booked');

            $table->dateTime('arrived_at')->nullable();
            $table->dateTime('cancelled_at')->nullable();

            $table->timestamps();

            $table->index([
                'doctor_id',
                'starts_at',
                'status'
            ]);

            $table->index([
                'branch_id',
                'starts_at',
                'status'
            ]);

            $table->index([
                'patient_id',
                'starts_at'
            ]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('appointments');
    }
};
