<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Gives the flood-alert / announcement delivery path the keys it needs
     * to stay idempotent when a queued job retries:
     *
     *  - announcements.iot_reading_id lets TriggerEmergencyOverride create
     *    exactly one emergency announcement per triggering sensor reading,
     *    even if the queued listener runs more than once.
     *  - report_notifications.announcement_id + the (user_id, announcement_id)
     *    unique index let DeliverAnnouncement write at most one in-app
     *    notification per resident per announcement, so a retried delivery
     *    job never doubles up.
     *
     * Both columns are nullable: ordinary report notifications keep a null
     * announcement_id, and announcements created by a human admin keep a
     * null iot_reading_id.
     */
    public function up(): void
    {
        Schema::table('announcements', function (Blueprint $table): void {
            $table->foreignId('iot_reading_id')->nullable()->after('sent_by')
                ->constrained('iot_readings')->nullOnDelete();
            // At most one announcement per triggering reading. NULL for
            // announcements composed by a human admin (NULLs don't collide).
            $table->unique('iot_reading_id');
        });

        Schema::table('report_notifications', function (Blueprint $table): void {
            $table->foreignId('announcement_id')->nullable()->after('report_id')
                ->constrained('announcements')->cascadeOnDelete();
            $table->unique(['user_id', 'announcement_id']);
        });
    }

    public function down(): void
    {
        Schema::table('report_notifications', function (Blueprint $table): void {
            $table->dropUnique(['user_id', 'announcement_id']);
            $table->dropConstrainedForeignId('announcement_id');
        });

        Schema::table('announcements', function (Blueprint $table): void {
            $table->dropUnique(['iot_reading_id']);
            $table->dropConstrainedForeignId('iot_reading_id');
        });
    }
};
