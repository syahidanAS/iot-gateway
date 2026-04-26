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
        Schema::create('data_streams', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('virtual_pin_id');
            $table->string('name');
            $table->string('data_type');
            $table->integer('min_value')->nullable();
            $table->integer('max_value')->nullable();
            $table->string('description')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('data_streams');
    }
};
