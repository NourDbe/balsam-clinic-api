<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reference_counters', function (Blueprint $table) {
            $table->id();

            $table->foreignId('branch_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->unsignedSmallInteger('year');
            $table->unsignedBigInteger('last_number')
                ->default(0);

            $table->unique(['branch_id', 'year']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reference_counters');
    }
};
