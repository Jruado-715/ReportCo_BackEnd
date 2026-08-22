<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void { Schema::table('reports', function(Blueprint $table): void { $table->string('resident_urgency')->default('normal')->after('description'); $table->index('resident_urgency'); }); }
    public function down(): void { Schema::table('reports', function(Blueprint $table): void { $table->dropIndex(['resident_urgency']); $table->dropColumn('resident_urgency'); }); }
};
