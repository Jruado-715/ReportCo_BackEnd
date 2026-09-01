<?php
namespace App\Services;
use App\Models\Announcement;
use App\Models\Report;
use App\Models\ReportNotification;
use App\Models\User;
class ReportNotificationService
{
    public function send(User $user, string $title, string $message, ?Report $report=null, string $type='report_update'): void { ReportNotification::create(['user_id'=>$user->id,'report_id'=>$report?->id,'type'=>$type,'title'=>$title,'message'=>$message]); }

    /**
     * In-app notification for an announcement. Keyed on (user, announcement)
     * so a retried DeliverAnnouncement job never creates a second copy for
     * the same resident. Independent of FCM — push is dispatched separately.
     */
    public function sendForAnnouncement(User $user, Announcement $announcement): void
    {
        ReportNotification::firstOrCreate(
            ['user_id' => $user->id, 'announcement_id' => $announcement->id],
            ['type' => $announcement->type, 'title' => $announcement->title, 'message' => $announcement->message],
        );
    }
}
