<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // Named `announcements` rather than `notifications` to avoid colliding
    // with Laravel's own built-in notifications table used by the
    // Notifiable trait (already in use on the User model).
    public function up(): void
    {
        Schema::create('announcements', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('sent_by')->constrained('users');
            $table->foreignId('purok_id')->nullable()->constrained('puroks')->nullOnDelete();
            $table->string('title');
            $table->text('message');
            $table->string('type'); // emergency | status_update | announcement
            $table->string('target_scope'); // barangay | purok | municipal_relay
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('announcements');
    }
};
