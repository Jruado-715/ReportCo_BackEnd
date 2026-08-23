<?php

namespace Tests\Feature;

use App\Events\FloodAlertCleared;
use App\Events\ThresholdCrossed;
use App\Exceptions\FcmInvalidTokenException;
use App\Jobs\DeliverAnnouncement;
use App\Jobs\SendPushNotification;
use App\Listeners\NotifyFloodAlertCleared;
use App\Listeners\TriggerEmergencyOverride;
use App\Models\Announcement;
use App\Models\IotReading;
use App\Models\Purok;
use App\Models\ReportNotification;
use App\Models\User;
use App\Services\FcmService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;
use Mockery\MockInterface;
use Tests\TestCase;

class NotificationDeliveryTest extends TestCase
{
    use RefreshDatabase;

    private function makeResident(Purok $purok, string $email, ?string $fcmToken = null): User
    {
        return User::create([
            'name' => 'Resident',
            'email' => $email,
            'password' => 'password',
            'role' => 'resident',
            'purok_id' => $purok->id,
            'fcm_token' => $fcmToken,
        ]);
    }

    public function test_threshold_crossed_creates_emergency_announcement_and_queues_delivery(): void
    {
        Queue::fake();
        $purok = Purok::create(['name' => 'Purok Galingan']);
        $admin = User::create([
            'name' => 'System', 'email' => 'system@example.com',
            'password' => 'password', 'role' => 'system_admin',
        ]);
        config(['services.flood_sensor.threshold_cm' => 150]);
        config(['services.flood_sensor.system_user_id' => $admin->id]);
        config(['services.flood_sensor.purok_id' => $purok->id]);

        $reading = IotReading::create([
            'water_level' => 180, 'threshold_crossed' => true, 'recorded_at' => now(),
        ]);

        (new TriggerEmergencyOverride())->handle(new ThresholdCrossed($reading));

        $this->assertDatabaseHas('announcements', [
            'type' => 'emergency',
            'target_scope' => 'purok',
            'purok_id' => $purok->id,
        ]);
        Queue::assertPushed(DeliverAnnouncement::class);
    }

    public function test_delivering_an_announcement_creates_in_app_notification_for_every_resident_in_scope(): void
    {
        Queue::fake();
        $purok = Purok::create(['name' => 'Purok Galingan']);
        $withDevice = $this->makeResident($purok, 'has-device@example.com', 'fcm-token-abc');
        $withoutDevice = $this->makeResident($purok, 'no-device@example.com', null);
        $admin = User::create([
            'name' => 'Admin', 'email' => 'admin@example.com',
            'password' => 'password', 'role' => 'barangay_admin', 'purok_id' => $purok->id,
        ]);

        $announcement = Announcement::create([
            'sent_by' => $admin->id,
            'purok_id' => $purok->id,
            'title' => 'Flood warning: evacuate low-lying areas now',
            'message' => 'Water level exceeded the safety threshold.',
            'type' => 'emergency',
            'target_scope' => 'purok',
        ]);

        (new DeliverAnnouncement($announcement))->handle(app(\App\Services\ReportNotificationService::class));

        // Both residents get the in-app notification, regardless of device registration.
        $this->assertDatabaseHas('report_notifications', [
            'user_id' => $withDevice->id, 'type' => 'emergency',
        ]);
        $this->assertDatabaseHas('report_notifications', [
            'user_id' => $withoutDevice->id, 'type' => 'emergency',
        ]);
        $this->assertSame(2, ReportNotification::count());

        // Only the resident with a registered device gets a push job.
        Queue::assertPushed(SendPushNotification::class, 1);
    }

    public function test_flood_alert_cleared_notifies_residents_in_app_and_via_push(): void
    {
        Queue::fake();
        $purok = Purok::create(['name' => 'Purok Galingan']);
        $resident = $this->makeResident($purok, 'resident@example.com', 'fcm-token-abc');
        config(['services.flood_sensor.purok_id' => $purok->id]);

        $reading = IotReading::create([
            'water_level' => 90, 'threshold_crossed' => false, 'recorded_at' => now(),
        ]);

        (new NotifyFloodAlertCleared())->handle(new FloodAlertCleared($reading));

        $this->assertDatabaseHas('report_notifications', [
            'user_id' => $resident->id, 'type' => 'flood_cleared',
        ]);
        Queue::assertPushed(SendPushNotification::class, 1);
    }

    public function test_send_push_notification_clears_an_invalid_fcm_token_without_retrying(): void
    {
        $purok = Purok::create(['name' => 'Purok Galingan']);
        $resident = $this->makeResident($purok, 'resident@example.com', 'stale-token');

        $this->mock(FcmService::class, function (MockInterface $mock): void {
            $mock->shouldReceive('sendToToken')
                ->once()
                ->andThrow(new FcmInvalidTokenException('token is unregistered'));
        });

        (new SendPushNotification($resident, 'Title', 'Body'))->handle(app(FcmService::class));

        $this->assertNull($resident->fresh()->fcm_token);
    }

    public function test_send_push_notification_leaves_a_valid_token_untouched(): void
    {
        $purok = Purok::create(['name' => 'Purok Galingan']);
        $resident = $this->makeResident($purok, 'resident@example.com', 'good-token');

        $this->mock(FcmService::class, function (MockInterface $mock): void {
            $mock->shouldReceive('sendToToken')->once();
        });

        (new SendPushNotification($resident, 'Title', 'Body'))->handle(app(FcmService::class));

        $this->assertSame('good-token', $resident->fresh()->fcm_token);
    }

    protected function tearDown(): void
    {
        Cache::forget('flood_alert_active');
        parent::tearDown();
    }
}
