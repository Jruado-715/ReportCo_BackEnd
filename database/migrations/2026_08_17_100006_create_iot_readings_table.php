<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('iot_readings', function (Blueprint $table): void {
            $table->id();
            $table->decimal('water_level', 6, 2); // cm from sensor
            $table->boolean('threshold_crossed')->default(false);
            $table->timestamp('recorded_at');
            $table->timestamps();

            $table->index('recorded_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('iot_readings');
    }
};
