<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('complaint_corpus_entries', function (Blueprint $table): void {
            $table->id();
            $table->text('raw_text');
            $table->string('label'); // road_damage | flooding | waste_management | electrical_hazard | public_safety | others
            $table->string('annotated_by')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('complaint_corpus_entries');
    }
};
