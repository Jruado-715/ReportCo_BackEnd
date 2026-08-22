<?php

namespace Database\Seeders;

use App\Models\Barangay;
use App\Models\Purok;
use App\Models\Street;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class MankilamSeeder extends Seeder
{
    /**
     * Demo/reference geography for ReportCo.
     *
     * Barangay Mankilam is an actual barangay of Tagum City.
     * The Purok/Street records below are reference/demo data assembled from
     * publicly documented Tagum City records and local geographic references.
     * They are not a substitute for the barangay's official master list.
     */
    public function run(): void
    {
        $barangay = Barangay::firstOrCreate(
            ['name' => 'Mankilam'],
        );

        // Publicly documented Mankilam reference geography collected from
        // PSA/Tagum City records, local addresses, and OpenStreetMap street
        // references. This is intentionally seeded as demo/reference data;
        // the barangay should provide its official master list before a live
        // deployment. Streets are only attached to a Purok where a public
        // source gives enough evidence for that relationship.
        $locations = [
            'Purok Abaca' => [],
            'Purok Caimito' => [],
            'Purok Capitol' => [],
            'Purok Cogon' => [],
            'Purok Dela Cruz' => [],
            'Purok Durian' => [],
            'Purok Galingan' => [
                'Aala Road',
                'R. Aala Road',
            ],
            'Purok Garcia' => [
                'Garcia Street',
            ],
            'Purok Garciaville' => [],
            'Purok Gulayan' => [
                'Gulayan Avenue',
            ],
            'Purok Ilocandia' => [
                'Barangay Road',
                'Capitol Circumferential Road',
                'Durian Street',
                'Ipil-Ipil Street',
                'Mahogany Street',
                'Yakal Street',
            ],
            'Purok Kalubiran' => [],
            'Purok Lemonsito' => [
                'Virginia Street',
            ],
            'Purok Magsanoc' => [
                'Capitol Avenue',
            ],
            'Purok Magkidong' => [],
            'Purok Magtalisay' => [],
            'Purok Magtaya' => [],
            'Purok Papaya' => [],
            'Purok Union' => [
                'Tadena 3 St.',
            ],
            'Purok Uraya' => [],
            'Purok Countryhomes' => [],
        ];


        $puroks = [];

        foreach ($locations as $purokName => $streets) {
            $purok = Purok::firstOrCreate(
                [
                    'barangay_id' => $barangay->id,
                    'name' => $purokName,
                ],
            );

            $puroks[$purokName] = $purok;

            foreach ($streets as $streetName) {
                Street::firstOrCreate([
                    'purok_id' => $purok->id,
                    'name' => $streetName,
                ]);
            }
        }

        // Development/demo accounts.
        $admin = User::updateOrCreate(
            ['email' => 'admin@mankilam.reportco.local'],
            [
                'name' => 'Mankilam Barangay Admin',
                'password' => Hash::make('Admin@12345'),
                'phone' => null,
                'role' => 'barangay_admin',
                'purok_id' => $puroks['Purok Galingan']->id,
            ],
        );

        User::updateOrCreate(
            ['email' => 'resident@mankilam.reportco.local'],
            [
                'name' => 'Mankilam Demo Resident',
                'password' => Hash::make('Resident@12345'),
                'phone' => null,
                'role' => 'resident',
                'purok_id' => $puroks['Purok Garcia']->id,
            ],
        );

        // Keep the existing system administrator and make the demo setup
        // idempotent. Do not overwrite a system admin password here.
        User::firstOrCreate(
            ['email' => 'system@reportco.local'],
            [
                'name' => 'ReportCo System',
                'password' => Hash::make('change-me-before-production'),
                'role' => 'system_admin',
            ],
        );
    }
}
