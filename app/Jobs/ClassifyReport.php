<?php

namespace App\Jobs;

use App\Enums\ReportCategory;
use App\Enums\ReportPriority;
use App\Models\KnowledgeBaseGuide;
use App\Models\Report;
use App\Services\MlClassifierService;
use App\Jobs\SendPushNotification;
use App\Services\ReportNotificationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

/**
 * Runs after a resident submits a report. Calls the SVM classifier,
 * applies the barangay's priority rules (Guided Resolution), and either
 * attaches a self-help guide or leaves the report for official review.
 *
 * This never handles the IoT Emergency Override path — that bypasses
 * classification entirely and goes straight to the notification layer,
 * since it didn't originate from a resident-submitted report. See
 * app/Listeners/TriggerEmergencyOverride.php.
 */
class ClassifyReport implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $backoff = 30;

    /** Categories the barangay has decided are typically low-urgency, surface-level issues. */
    private const LOW_PRIORITY_CATEGORIES = [
        ReportCategory::WasteManagement,
        ReportCategory::Others,
    ];

    /** Categories that always warrant direct official attention. */
    private const HIGH_PRIORITY_CATEGORIES = [
        ReportCategory::ElectricalHazard,
        ReportCategory::PublicSafety,
    ];

    public function __construct(
        private readonly Report $report,
    ) {}

    public function handle(MlClassifierService $classifier): void
    {
        $result = $classifier->classify($this->report->description);
        $category = ReportCategory::from($result['category']);
        $priority = $this->assignPriority($category, (float) ($result['confidence'] ?? 0));
        $priority = $this->applyResidentUrgency($priority, (string) $this->report->resident_urgency);
        $automaticEmergency = $this->detectEmergency($category, $this->report->description);
        $existingEmergency = (bool) $this->report->emergency_override;
        $emergency = $existingEmergency || $automaticEmergency;

        $this->report->update([
            'category' => $category,
            'classified_by_svm' => true,
            'emergency_override' => $emergency,
            'emergency_reason' => $existingEmergency
                ? $this->report->emergency_reason
                : ($automaticEmergency ? 'Automatic emergency rule matched the classified complaint.' : null),
            'emergency_triggered_at' => $existingEmergency
                ? $this->report->emergency_triggered_at
                : ($automaticEmergency ? now() : null),
            'priority' => $emergency ? ReportPriority::Emergency : $priority,
        ]);

        if ($emergency && !$existingEmergency) {
            app(ReportNotificationService::class)->send($this->report->user, 'Urgent report', 'Your report #'.$this->report->id.' requires immediate barangay attention.', $this->report, 'emergency');
            SendPushNotification::dispatch($this->report->user, 'Urgent report', 'Your report #'.$this->report->id.' requires immediate barangay attention.');
            return;
        }

        if ($priority === ReportPriority::Low) {
            $this->attachSelfHelpGuide($category);
        }

        // status stays "received" either way — Guided Resolution informs the
        // resident immediately, but only officials mark it in_progress/resolved.
    }

    private function assignPriority(ReportCategory $category, float $confidence): ReportPriority
    {
        // Low-confidence classifications default to medium so an
        // uncertain category never gets silently downgraded to "low".
        if ($confidence < 0.6) {
            return ReportPriority::Medium;
        }

        if (in_array($category, self::HIGH_PRIORITY_CATEGORIES, true)) {
            return ReportPriority::High;
        }

        if (in_array($category, self::LOW_PRIORITY_CATEGORIES, true)) {
            return ReportPriority::Low;
        }

        return ReportPriority::Medium;
    }

    private function detectEmergency(ReportCategory $category, string $description): bool
    {
        if (in_array($category, [ReportCategory::ElectricalHazard, ReportCategory::PublicSafety], true)) return true;
        if ($category === ReportCategory::Flooding && preg_match('/\b(danger|urgent|emergency|trapped|drowning|evacuate|evacuation|delikado|lubog|baha na)\b/i', $description) === 1) return true;
        return preg_match('/\b(fire|sunog|nasusunog)\b/i', $description) === 1;
    }


    private function applyResidentUrgency(ReportPriority $priority, string $urgency): ReportPriority
    {
        return match ($urgency) {
            'emergency' => ReportPriority::Emergency,
            'urgent' => $priority === ReportPriority::Emergency ? $priority : ReportPriority::High,
            'important' => in_array($priority, [ReportPriority::Low, ReportPriority::Unclassified], true) ? ReportPriority::Medium : $priority,
            default => $priority,
        };
    }

    private function attachSelfHelpGuide(ReportCategory $category): void
    {
        $guide = KnowledgeBaseGuide::where('category', $category)->first();

        if ($guide === null) {
            return; // knowledge base not yet populated for this category — resident gets generic advice via the app
        }

        SendPushNotification::dispatch(
            $this->report->user,
            title: 'Here\'s how you can resolve this',
            body: $guide->guide_text,
        );
    }

    public function failed(Throwable $e): void
    {
        logger()->error('ClassifyReport failed', [
            'report_id' => $this->report->id,
            'error' => $e->getMessage(),
        ]);
        // Report stays priority=unclassified and visible in the admin
        // queue as "needs manual review" rather than silently disappearing.
    }
}
