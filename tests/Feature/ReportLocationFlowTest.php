<?php

namespace Tests\Feature;

use App\Jobs\ClassifyReport;
use App\Models\Purok;
use App\Models\Street;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Tests\TestCase;

class ReportLocationFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_report_rejects_a_street_from_another_purok(): void
    {
        Queue::fake();
        $a=Purok::create(['name'=>'Purok 1']);
        $b=Purok::create(['name'=>'Purok 2']);
        $street=Street::create(['purok_id'=>$b->id,'name'=>'Mabini Street']);
        $user=User::create(['name'=>'Resident','email'=>'resident@example.com','password'=>'password','role'=>'resident','purok_id'=>$a->id]);
        $token=Str::random(80);
        $user->apiTokens()->create(['token_hash'=>hash('sha256',$token),'name'=>'test']);

        $response=$this->withHeader('Authorization','Bearer '.$token)->postJson('/api/reports',[
            'description'=>'Broken streetlight', 'purok_id'=>$a->id, 'street_id'=>$street->id,
            'latitude'=>7.44, 'longitude'=>125.80, 'resident_urgency'=>'normal',
        ]);

        $response->assertStatus(422);
        Queue::assertNothingPushed();
    }

    public function test_report_submission_queues_classification_with_matching_location(): void
    {
        Queue::fake();
        $p=Purok::create(['name'=>'Purok 1']);
        $street=Street::create(['purok_id'=>$p->id,'name'=>'Mabini Street']);
        $user=User::create(['name'=>'Resident','email'=>'resident2@example.com','password'=>'password','role'=>'resident','purok_id'=>$p->id]);
        $token=Str::random(80);
        $user->apiTokens()->create(['token_hash'=>hash('sha256',$token),'name'=>'test']);

        $response=$this->withHeader('Authorization','Bearer '.$token)->postJson('/api/reports',[
            'description'=>'Small litter near the road', 'purok_id'=>$p->id, 'street_id'=>$street->id,
            'latitude'=>7.44, 'longitude'=>125.80, 'resident_urgency'=>'normal',
        ]);

        $response->assertStatus(202)->assertJsonPath('data.purok.id',$p->id)->assertJsonPath('data.street.id',$street->id);
        Queue::assertPushed(ClassifyReport::class);
    }
    public function test_report_submission_succeeds_without_a_street(): void
    {
        Queue::fake();
        $p=Purok::create(['name'=>'Purok Without Street']);
        $user=User::create(['name'=>'Resident No Street','email'=>'resident-nostreet@example.com','password'=>'password','role'=>'resident','purok_id'=>$p->id]);
        $token=Str::random(80);
        $user->apiTokens()->create(['token_hash'=>hash('sha256',$token),'name'=>'test']);

        $response=$this->withHeader('Authorization','Bearer '.$token)->postJson('/api/reports',[
            'description'=>'Flooding in the area', 'purok_id'=>$p->id, 'street_id'=>null,
            'resident_urgency'=>'urgent', 'latitude'=>7.44, 'longitude'=>125.80,
        ]);

        $response->assertStatus(202)
            ->assertJsonPath('data.purok.id',$p->id)
            ->assertJsonPath('data.street',null);
        Queue::assertPushed(ClassifyReport::class);
    }

    public function test_emergency_urgency_is_persisted_as_emergency(): void
    {
        Queue::fake();
        $p=Purok::create(['name'=>'Purok Emergency']);
        $user=User::create(['name'=>'Emergency Resident','email'=>'resident-emergency@example.com','password'=>'password','role'=>'resident','purok_id'=>$p->id]);
        $token=Str::random(80);
        $user->apiTokens()->create(['token_hash'=>hash('sha256',$token),'name'=>'test']);

        $response=$this->withHeader('Authorization','Bearer '.$token)->postJson('/api/reports',[
            'description'=>'Person is trapped by flood water', 'purok_id'=>$p->id,
            'resident_urgency'=>'emergency', 'latitude'=>7.44, 'longitude'=>125.80,
        ]);

        $response->assertStatus(202)
            ->assertJsonPath('data.resident_urgency','emergency')
            ->assertJsonPath('data.emergency_override',true)
            ->assertJsonPath('data.priority','emergency');
        Queue::assertPushed(ClassifyReport::class);
    }

}
