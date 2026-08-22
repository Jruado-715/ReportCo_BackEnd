<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // Runs against the `users` table Laravel already scaffolded for you
    // (0001_01_01_000000_create_users_table.php) — this only adds the
    // ReportCo-specific columns rather than recreating the table.
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->string('phone')->nullable()->after('email');
            $table->string('role')->default('resident')->after('phone'); // resident | barangay_admin | system_admin
            $table->foreignId('purok_id')->nullable()->after('role')->constrained('puroks')->nullOnDelete();
            $table->string('fcm_token')->nullable()->after('purok_id'); // latest push-notification device token
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('purok_id');
            $table->dropColumn(['phone', 'role', 'fcm_token']);
        });
    }
};
