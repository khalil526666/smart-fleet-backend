<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Vehicle;
use App\Models\Driver;
use App\Models\Maintenance;
use App\Models\FuelRecord;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Database\Seeders\MissionSeeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $users = [
            [
                'name' => 'Admin',
                'email' => 'admin@fleet.local',
                'password' => Hash::make('password'),
                'role' => 'admin',
            ],
            [
                'name' => 'Fleet Manager',
                'email' => 'manager@fleet.local',
                'password' => Hash::make('password'),
                'role' => 'gestionnaire',
            ],
            [
                'name' => 'Khalil',
                'email' => 'khalil@gmail.com',
                'password' => Hash::make('password'),
                'role' => 'admin',
            ],
        ];

        foreach ($users as $data) {
            User::updateOrCreate(['email' => $data['email']], $data);
        }

        $v1 = Vehicle::updateOrCreate(
            ['license_plate' => 'ABC-1234'],
            [
                'brand' => 'Toyota',
                'model' => 'Hilux',
                'year' => 2022,
                'mileage' => 15000,
                'status' => 'active',
            ]
        );

        Vehicle::updateOrCreate(
            ['license_plate' => 'XYZ-5678'],
            [
                'brand' => 'Ford',
                'model' => 'Transit',
                'year' => 2021,
                'mileage' => 45000,
                'status' => 'maintenance',
            ]
        );

        Driver::updateOrCreate(
            ['license_number' => 'DL-001'],
            [
                'name' => 'Mehdi Trabelsi',
                'phone' => '+21698123456',
                'address' => 'Av. Habib Bourguiba, Tunis',
                'vehicle_id' => $v1->id,
            ]
        );

        Driver::updateOrCreate(
            ['license_number' => 'DL-002'],
            [
                'name' => 'Sarra Ben Amor',
                'phone' => '+21623987654',
                'address' => 'Rue de la Liberté, Sfax',
                'vehicle_id' => null,
            ]
        );

        Maintenance::firstOrCreate(
            [
                'vehicle_id' => $v1->id,
                'date' => now()->subDays(30)->toDateString(),
                'maintenance_type' => 'Oil Change',
            ],
            [
                'description' => 'Regular oil and filter change',
                'odometer' => 14000,
                'cost' => 85.50,
            ]
        );

        // Fuel records with odometer: enough segments to detect anomalies
        $fuelSeed = [
            ['days_ago' => 28, 'odometer' => 12000, 'liters' => 40, 'price' => 60.00],
            ['days_ago' => 20, 'odometer' => 13000, 'liters' => 42, 'price' => 63.00],
            ['days_ago' => 12, 'odometer' => 14000, 'liters' => 41, 'price' => 61.50],
            // Anomalous: much higher liters for same distance (should trigger)
            ['days_ago' => 5, 'odometer' => 15000, 'liters' => 65, 'price' => 97.50],
        ];

        foreach ($fuelSeed as $r) {
            FuelRecord::firstOrCreate(
                [
                    'vehicle_id' => $v1->id,
                    'date' => now()->subDays($r['days_ago'])->toDateString(),
                    'odometer' => $r['odometer'],
                ],
                [
                    'liters' => $r['liters'],
                    'price' => $r['price'],
                ]
            );
        }

        $this->call(MissionSeeder::class);
    }
}
