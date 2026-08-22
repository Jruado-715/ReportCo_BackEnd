<?php
namespace App\Services;
use App\Models\Report;
use App\Models\ReportNotification;
use App\Models\User;
class ReportNotificationService
{
    public function send(User $user, string $title, string $message, ?Report $report=null, string $type='report_update'): void { ReportNotification::create(['user_id'=>$user->id,'report_id'=>$report?->id,'type'=>$type,'title'=>$title,'message'=>$message]); }
}
