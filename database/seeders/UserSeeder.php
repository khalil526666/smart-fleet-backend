<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        $users = [
            [
                'email' => 'admin@fleet.local',
                'name' => 'Admin',
                'password' => Hash::make('password'),
                'role' => 'admin',
            ],
            [
                'email' => 'manager@fleet.local',
                'name' => 'Fleet Manager',
                'password' => Hash::make('password'),
                'role' => 'gestionnaire',
            ],
            [
                'email' => 'khalil@gmail.com',
                'name' => 'Khalil',
                'password' => Hash::make('password'),
                'role' => 'admin',
            ],
            [
                'email' => 'admin@gmail.com',
                'name' => 'Admin',
                'password' => Hash::make('password'),
                'role' => 'admin',
            ],
        ];

        foreach ($users as $data) {
            User::updateOrCreate(
                ['email' => $data['email']],
                $data
            );
        }
    }
}
