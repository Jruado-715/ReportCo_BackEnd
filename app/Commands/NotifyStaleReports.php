<?php

namespace App\Console\Commands;

use App\Models\Report;
use App\Models\ReportNotification;
use App\Models\User;
use App\Services\ReportNotificationService;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class NotifyStaleReports extends Command
{
    protected $signature = 'reports:notify-stale';

    protected $description = 'Notify barangay admins about reports that have missed their SLA update window.';

    public function handle(ReportNotificationService $notifications): int
    {
        $staleReports = Report::query()->stale()->get();

        if ($staleReports->isEmpty()) {
            $this->info('No stale reports.');

            return self::SUCCESS;
        }

        $admins = User::query()->where('role', 'barangay_admin')->get();
        $cooldownHours = (int) config('services.sla.renotify_after_hours', 24);
        $notifiedCount = 0;

        foreach ($staleReports as $report) {
            if ($this->wasRecentlyNotified($report, $cooldownHours)) {
                continue;
            }

            foreach ($admins as $admin) {
                $notifications->send(
                    $admin,
                    'Report needs attention',
                    sprintf(
                        'Report #%d ("%s") has had no update within its SLA window for %s priority.',
                        $report->id,
                        Str::limit($report->description, 60),
                        $report->priority->value,
                    ),
                    $report,
                    'stale_report',
                );
            }

            $notifiedCount++;
        }

        $this->info(sprintf(
            'Found %d stale report(s), notified admins about %d (skipped %d already notified within %dh).',
            $staleReports->count(),
            $notifiedCount,
            $staleReports->count() - $notifiedCount,
            $cooldownHours,
        ));

        return self::SUCCESS;
    }

    private function wasRecentlyNotified(Report $report, int $cooldownHours): bool
    {
        return ReportNotification::query()
            ->where('report_id', $report->id)
            ->where('type', 'stale_report')
            ->where('created_at', '>=', now()->subHours($cooldownHours))
            ->exists();
    }
}
