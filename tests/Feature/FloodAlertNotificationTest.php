<?php

namespace Tests\Feature;

use App\Events\ThresholdCrossed;
use App\Jobs\DeliverAnnouncement;
use App\Jobs\SendPushNotification;
use App\Listeners\TriggerEmergencyOverride;
use App\Models\Announcement;
use App\Models\IotReading;
use App\Models\Purok;
use App\Models\ReportNotification;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * End-to-end coverage for the IoT flood-alert → in-app notification flow,
 * exercised through the real /api/iot/readings endpoint wherever possible.
 */
class FloodAlertNotificationTest extends TestCase
{
    use RefreshDatabase;

    private const DEVICE_KEY = 'test-device-key';

    private Purok $purok;
    private User $withToken;
    private User $withoutToken;

    protected function setUp(): void
    {
        parent::setUp();

        $this->purok = Purok::create(['name' => 'Purok Riverside']);

        $systemUser = User::create([
            'name' => 'ReportCo System', 'email' => 'system@reportco.test',
            'password' => 'password', 'role' => 'system_admin',
        ]);

        $this->withToken = $this->makeResident('has-device@reportco.test', 'fcm-token-abc');
        $this->withoutToken = $this->makeResident('no-device@reportco.test', null);

        config([
            'services.flood_sensor.threshold_cm' => 150,
            'services.flood_sensor.device_key' => self::DEVICE_KEY,
            'services.flood_sensor.system_user_id' => $systemUser->id,
            'services.flood_sensor.purok_id' => null, // barangay-wide
        ]);
    }

    private function makeResident(string $email, ?string $fcmToken): User
    {
        return User::create([
            'name' => 'Resident', 'email' => $email, 'password' => 'password',
            'role' => 'resident', 'purok_id' => $this->purok->id, 'fcm_token' => $fcmToken,
        ]);
    }

    private function postReading(float $waterLevel): void
    {
        $this->postJson('/api/iot/readings', ['water_level' => $waterLevel], [
            'X-Device-Key' => self::DEVICE_KEY,
        ])->assertCreated();
    }

    public function test_reading_below_threshold_creates_no_flood_alert(): void
    {
        Event::fake([ThresholdCrossed::class]);

        $this->postReading(90);

        Event::assertNotDispatched(ThresholdCrossed::class);
        $this->assertDatabaseCount('announcements', 0);
        $this->assertDatabaseCount('report_notifications', 0);
    }

    public function test_crossing_the_threshold_creates_one_alert_and_in_app_notifications_for_all_residents(): void
    {
        $this->postReading(180);

        $this->assertSame(1, Announcement::where('type', 'emergency')->count());
        $this->assertDatabaseHas('report_notifications', [
            'user_id' => $this->withToken->id, 'type' => 'emergency',
        ]);
        $this->assertDatabaseHas('report_notifications', [
            'user_id' => $this->withoutToken->id, 'type' => 'emergency',
        ]);
        $this->assertSame(2, ReportNotification::count());
    }

    public function test_repeated_readings_above_threshold_do_not_create_duplicate_alerts_or_notifications(): void
    {
        $this->postReading(180);
        $this->postReading(185);
        $this->postReading(190);
        $this->postReading(210);

        $this->assertSame(1, Announcement::count());
        $this->assertSame(2, ReportNotification::count());
    }

    public function test_resident_without_fcm_token_still_receives_in_app_notification(): void
    {
        // Fake only the push job so the listener + delivery job still run.
        Queue::fake([SendPushNotification::class]);

        $this->postReading(180);

        $this->assertDatabaseHas('report_notifications', [
            'user_id' => $this->withoutToken->id, 'type' => 'emergency',
        ]);
        $this->assertDatabaseHas('report_notifications', [
            'user_id' => $this->withToken->id, 'type' => 'emergency',
        ]);
        // Only the resident with a registered device gets a push job.
        Queue::assertPushed(SendPushNotification::class, 1);
    }

    public function test_delivery_job_is_idempotent_when_it_retries(): void
    {
        $this->postReading(180);
        $announcement = Announcement::firstOrFail();

        // Simulate the queue running the same job three times (tries = 3).
        foreach (range(1, 3) as $_) {
            (new DeliverAnnouncement($announcement))
                ->handle(app(\App\Services\ReportNotificationService::class));
        }

        $this->assertSame(2, ReportNotification::count());
    }

    public function test_emergency_override_listener_is_idempotent_for_the_same_reading(): void
    {
        Queue::fake();

        $reading = IotReading::create([
            'water_level' => 200, 'threshold_crossed' => true, 'recorded_at' => now(),
        ]);

        foreach (range(1, 3) as $_) {
            (new TriggerEmergencyOverride())->handle(new ThresholdCrossed($reading));
        }

        $this->assertSame(1, Announcement::where('iot_reading_id', $reading->id)->count());
    }

    public function test_fcm_failure_does_not_prevent_in_app_notification(): void
    {
        // Every push attempt blows up with a transient (non-token) error.
        $this->mock(\App\Services\FcmService::class, function ($mock): void {
            $mock->shouldReceive('sendToToken')
                ->andThrow(new \RuntimeException('FCM 503 service unavailable'));
        });

        $this->postReading(180);
        (new DeliverAnnouncement(Announcement::firstOrFail()))
            ->handle(app(\App\Services\ReportNotificationService::class));

        // Both residents still have their in-app notification.
        $this->assertSame(2, ReportNotification::count());
        // The token was a transient failure, not an invalid-token response,
        // so it is left in place.
        $this->assertSame('fcm-token-abc', $this->withToken->fresh()->fcm_token);
    }

    public function test_flood_cleared_notifies_each_resident_once(): void
    {
        $this->postReading(180); // open the episode
        $this->postReading(80);  // clear it

        // Exactly one "flood cleared" in-app notification per resident,
        // i.e. the listener is not registered twice.
        $this->assertSame(1, ReportNotification::where('type', 'flood_cleared')->where('user_id', $this->withToken->id)->count());
        $this->assertSame(1, ReportNotification::where('type', 'flood_cleared')->where('user_id', $this->withoutToken->id)->count());
    }

    protected function tearDown(): void
    {
        Cache::forget('flood_alert_active');
        parent::tearDown();
    }
}
