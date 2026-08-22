<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void { Schema::create('streets', function (Blueprint $table): void { $table->id(); $table->foreignId('purok_id')->constrained('puroks')->restrictOnDelete(); $table->string('name'); $table->timestamps(); $table->unique(['purok_id', 'name']); }); }
    public function down(): void { Schema::dropIfExists('streets'); }
};
