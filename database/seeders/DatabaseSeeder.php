<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Database\Seeders\KnowledgeBaseGuideSeeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        User::firstOrCreate(
            ['email' => 'system@reportco.local'],
            [
                'name' => 'ReportCo System',
                'password' => Hash::make('change-me-before-production'),
                'role' => 'system_admin',
            ]
        );

        $this->call([MankilamSeeder::class, KnowledgeBaseGuideSeeder::class]);
    }
}
