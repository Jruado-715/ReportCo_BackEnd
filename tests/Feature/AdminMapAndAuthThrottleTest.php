<?php

namespace Tests\Feature;

use App\Models\Purok;
use App\Models\Report;
use App\Models\Street;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class AdminMapAndAuthThrottleTest extends TestCase
{
    use RefreshDatabase;

    private function tokenFor(User $user): string
    {
        $token = Str::random(80);
        $user->apiTokens()->create(['token_hash' => hash('sha256', $token), 'name' => 'test']);

        return $token;
    }

    public function test_resident_cannot_access_admin_map_reports(): void
    {
        $purok = Purok::create(['name' => 'Purok 1']);
        $resident = User::create([
            'name' => 'Resident', 'email' => 'resident@example.com',
            'password' => 'password', 'role' => 'resident', 'purok_id' => $purok->id,
        ]);

        $response = $this->withHeader('Authorization', 'Bearer '.$this->tokenFor($resident))
            ->getJson('/api/admin/map/reports');

        $response->assertStatus(403);
    }

    public function test_admin_can_access_admin_map_reports_with_geo_tagged_data(): void
    {
        $purok = Purok::create(['name' => 'Purok 1']);
        $street = Street::create(['purok_id' => $purok->id, 'name' => 'Mabini Street']);
        $resident = User::create([
            'name' => 'Resident', 'email' => 'resident3@example.com',
            'password' => 'password', 'role' => 'resident', 'purok_id' => $purok->id,
        ]);
        $admin = User::create([
            'name' => 'Admin', 'email' => 'admin@example.com',
            'password' => 'password', 'role' => 'barangay_admin', 'purok_id' => $purok->id,
        ]);

        Report::create([
            'user_id' => $resident->id, 'purok_id' => $purok->id, 'street_id' => $street->id,
            'description' => 'Flooded road', 'resident_urgency' => 'urgent',
            'latitude' => 7.447, 'longitude' => 125.807,
        ]);

        $response = $this->withHeader('Authorization', 'Bearer '.$this->tokenFor($admin))
            ->getJson('/api/admin/map/reports');

        $response->assertStatus(200)
            ->assertJsonPath('data.0.latitude', '7.4470000')
            ->assertJsonPath('data.0.longitude', '125.8070000');
    }

    public function test_admin_can_view_a_report_through_admin_detail_endpoint(): void
    {
        $purok = Purok::create(['name' => 'Purok 1']);
        $street = Street::create(['purok_id' => $purok->id, 'name' => 'Mabini Street']);
        $resident = User::create([
            'name' => 'Resident', 'email' => 'resident-detail@example.com',
            'password' => 'password', 'role' => 'resident', 'purok_id' => $purok->id,
        ]);
        $admin = User::create([
            'name' => 'Admin', 'email' => 'admin-detail@example.com',
            'password' => 'password', 'role' => 'barangay_admin', 'purok_id' => $purok->id,
        ]);
        $report = Report::create([
            'user_id' => $resident->id, 'purok_id' => $purok->id, 'street_id' => $street->id,
            'description' => 'Flooded road', 'resident_urgency' => 'urgent',
            'latitude' => 7.447, 'longitude' => 125.807,
        ]);

        $response = $this->withHeader('Authorization', 'Bearer '.$this->tokenFor($admin))
            ->getJson('/api/admin/reports/'.$report->id);

        $response->assertStatus(200)
            ->assertJsonPath('data.id', $report->id)
            ->assertJsonPath('data.street.name', 'Mabini Street');
    }

    public function test_admin_map_reports_does_not_break_with_no_reports_yet(): void
    {
        $purok = Purok::create(['name' => 'Purok 1']);
        $admin = User::create([
            'name' => 'Admin', 'email' => 'admin2@example.com',
            'password' => 'password', 'role' => 'barangay_admin', 'purok_id' => $purok->id,
        ]);

        $response = $this->withHeader('Authorization', 'Bearer '.$this->tokenFor($admin))
            ->getJson('/api/admin/map/reports');

        $response->assertStatus(200)->assertJsonPath('data', []);
    }

    public function test_login_is_rate_limited_after_five_attempts_per_minute(): void
    {
        $purok = Purok::create(['name' => 'Purok 1']);
        User::create([
            'name' => 'Resident', 'email' => 'throttle@example.com',
            'password' => 'password', 'role' => 'resident', 'purok_id' => $purok->id,
        ]);

        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/auth/login', ['email' => 'throttle@example.com', 'password' => 'wrong-password'])
                ->assertStatus(401);
        }

        $this->postJson('/api/auth/login', ['email' => 'throttle@example.com', 'password' => 'wrong-password'])
            ->assertStatus(429);
    }

    public function test_register_is_rate_limited_after_five_attempts_per_minute(): void
    {
        $purok = Purok::create(['name' => 'Purok 1']);

        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/auth/register', [
                'name' => 'Resident', 'email' => "resident-throttle-$i@example.com",
                'password' => 'password123', 'password_confirmation' => 'password123',
                'purok_id' => $purok->id,
            ])->assertStatus(200);
        }

        $this->postJson('/api/auth/register', [
            'name' => 'Resident', 'email' => 'resident-throttle-overflow@example.com',
            'password' => 'password123', 'password_confirmation' => 'password123',
            'purok_id' => $purok->id,
        ])->assertStatus(429);
    }
}
