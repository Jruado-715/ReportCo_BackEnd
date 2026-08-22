<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reports', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('purok_id')->nullable()->constrained('puroks')->nullOnDelete();
            $table->text('description');
            $table->string('photo_path')->nullable();
            $table->decimal('latitude', 10, 7);
            $table->decimal('longitude', 10, 7);
            $table->string('category')->default('others');
            $table->string('priority')->default('unclassified'); // unclassified | low | medium | high | emergency
            $table->string('status')->default('received'); // received | in_progress | resolved
            $table->boolean('classified_by_svm')->default(false);
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();

            $table->index(['purok_id', 'category']);
            $table->index(['latitude', 'longitude']); // supports the K-Means / heatmap query
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reports');
    }
};
