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
        Schema::create('automation_actions', function (Blueprint $table) {
            $table->id();

            $table->foreignId('automation_id')
                ->constrained('automations')
                ->cascadeOnDelete();

            $table->string('target_pin'); // V0, V1, V2
            $table->tinyInteger('value'); // 0 / 1

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('automation_actions');
    }
};
