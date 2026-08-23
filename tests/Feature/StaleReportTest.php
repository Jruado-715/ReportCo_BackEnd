<?php

namespace Tests\Feature;

use App\Enums\ReportPriority;
use App\Enums\ReportStatus;
use App\Models\Purok;
use App\Models\Report;
use App\Models\ReportNotification;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StaleReportTest extends TestCase
{
    use RefreshDatabase;

    private Purok $purok;
    private User $resident;

    protected function setUp(): void
    {
        parent::setUp();

        $this->purok = Purok::create(['name' => 'Purok Galingan']);
        $this->resident = User::create([
            'name' => 'Resident', 'email' => 'resident@example.com',
            'password' => 'password', 'role' => 'resident', 'purok_id' => $this->purok->id,
        ]);

        config(['services.sla.stale_after_hours' => [
            'emergency' => 2, 'high' => 24, 'medium' => 72, 'low' => 168, 'unclassified' => 72,
        ]]);
        config(['services.sla.renotify_after_hours' => 24]);
    }

    /** Creates a report and backdates updated_at without Eloquent re-touching it. */
    private function reportUpdatedHoursAgo(ReportPriority $priority, ReportStatus $status, float $hoursAgo): Report
    {
        $report = Report::create([
            'user_id' => $this->resident->id, 'purok_id' => $this->purok->id,
            'description' => 'Test report', 'priority' => $priority->value, 'status' => $status->value,
            'latitude' => 7.44, 'longitude' => 125.80,
        ]);

        $report->timestamps = false;
        $report->forceFill(['updated_at' => now()->subHours($hoursAgo)])->save();

        return $report->fresh();
    }

    public function test_emergency_report_is_stale_after_its_two_hour_window_but_not_before(): void
    {
        $fresh = $this->reportUpdatedHoursAgo(ReportPriority::Emergency, ReportStatus::Received, 1);
        $stale = $this->reportUpdatedHoursAgo(ReportPriority::Emergency, ReportStatus::Received, 3);

        $staleIds = Report::query()->stale()->pluck('id');

        $this->assertFalse($staleIds->contains($fresh->id));
        $this->assertTrue($staleIds->contains($stale->id));
    }

    public function test_low_priority_report_uses_its_own_longer_window(): void
    {
        // 30 hours would be stale for "high" priority but not for "low".
        $report = $this->reportUpdatedHoursAgo(ReportPriority::Low, ReportStatus::Received, 30);

        $this->assertFalse(Report::query()->stale()->pluck('id')->contains($report->id));
    }

    public function test_resolved_reports_are_never_stale_regardless_of_age(): void
    {
        $report = $this->reportUpdatedHoursAgo(ReportPriority::Emergency, ReportStatus::Resolved, 1000);

        $this->assertFalse(Report::query()->stale()->pluck('id')->contains($report->id));
    }

    public function test_notify_stale_reports_command_notifies_every_barangay_admin(): void
    {
        $admin1 = User::create([
            'name' => 'Admin One', 'email' => 'admin1@example.com',
            'password' => 'password', 'role' => 'barangay_admin', 'purok_id' => $this->purok->id,
        ]);
        $admin2 = User::create([
            'name' => 'Admin Two', 'email' => 'admin2@example.com',
            'password' => 'password', 'role' => 'barangay_admin', 'purok_id' => $this->purok->id,
        ]);
        $report = $this->reportUpdatedHoursAgo(ReportPriority::High, ReportStatus::InProgress, 30);

        $this->artisan('reports:notify-stale')->assertSuccessful();

        $this->assertDatabaseHas('report_notifications', [
            'user_id' => $admin1->id, 'report_id' => $report->id, 'type' => 'stale_report',
        ]);
        $this->assertDatabaseHas('report_notifications', [
            'user_id' => $admin2->id, 'report_id' => $report->id, 'type' => 'stale_report',
        ]);
    }

    public function test_notify_stale_reports_command_does_not_spam_within_the_cooldown_window(): void
    {
        $admin = User::create([
            'name' => 'Admin', 'email' => 'admin@example.com',
            'password' => 'password', 'role' => 'barangay_admin', 'purok_id' => $this->purok->id,
        ]);
        $report = $this->reportUpdatedHoursAgo(ReportPriority::High, ReportStatus::InProgress, 30);

        $this->artisan('reports:notify-stale')->assertSuccessful();
        $this->assertSame(1, ReportNotification::where('report_id', $report->id)->count());

        // Running it again immediately (simulating the next hourly tick)
        // should not create a second notification for the same report.
        $this->artisan('reports:notify-stale')->assertSuccessful();
        $this->assertSame(1, ReportNotification::where('report_id', $report->id)->count());
    }
}
