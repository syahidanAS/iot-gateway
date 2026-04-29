<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('automation_conditions', function (Blueprint $table) {
            $table->id();

            $table->foreignId('automation_id')
                ->constrained('automations')
                ->cascadeOnDelete();

            // sensor | time
            $table->enum('type', ['sensor', 'time']);

            // SENSOR MODE
            $table->string('sensor_tag')->nullable(); // temperature, humidity, pressure
            $table->string('operator')->nullable();    // > < =
            $table->double('value')->nullable();

            // TIME MODE
            $table->time('time')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('automation_conditions');
    }
};
