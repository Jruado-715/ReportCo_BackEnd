<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void { Schema::table('reports', function (Blueprint $table): void { $table->foreignId('street_id')->nullable()->after('purok_id')->constrained('streets')->restrictOnDelete(); $table->boolean('emergency_override')->default(false)->after('classified_by_svm'); $table->text('emergency_reason')->nullable()->after('emergency_override'); $table->timestamp('emergency_triggered_at')->nullable()->after('emergency_reason'); $table->index(['street_id', 'category']); }); }
    public function down(): void { Schema::table('reports', function (Blueprint $table): void { $table->dropConstrainedForeignId('street_id'); $table->dropColumn(['emergency_override','emergency_reason','emergency_triggered_at']); }); }
};
