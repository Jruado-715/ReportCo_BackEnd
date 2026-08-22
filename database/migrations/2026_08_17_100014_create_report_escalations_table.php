<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void { Schema::create('report_escalations', function (Blueprint $table): void { $table->id(); $table->foreignId('report_id')->constrained()->cascadeOnDelete(); $table->foreignId('escalated_by')->constrained('users'); $table->string('receiving_office'); $table->text('reason'); $table->text('notes')->nullable(); $table->string('status')->default('pending'); $table->string('external_reference')->nullable(); $table->timestamp('escalated_at'); $table->timestamp('resolved_at')->nullable(); $table->timestamps(); $table->index(['report_id','status']); }); }
    public function down(): void { Schema::dropIfExists('report_escalations'); }
};
