<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void { Schema::table('puroks', function (Blueprint $table): void { $table->foreignId('barangay_id')->nullable()->after('id')->constrained('barangays')->nullOnDelete(); $table->index('barangay_id'); }); }
    public function down(): void { Schema::table('puroks', function (Blueprint $table): void { $table->dropConstrainedForeignId('barangay_id'); }); }
};
