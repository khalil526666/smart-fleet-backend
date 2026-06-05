<?php

namespace Database\Seeders;

use App\Models\Mission;
use App\Models\User;
use Illuminate\Database\Seeder;

class MissionSeeder extends Seeder
{
    public function run(): void
    {
        // Assign sample missions to a "chauffeur" represented by role "user" (or fallback to any non-admin).
        $chauffeur = User::query()->where('role', 'user')->first()
            ?? User::query()->where('role', 'gestionnaire')->first()
            ?? User::query()->first();

        if (! $chauffeur) return;

        $m1 = Mission::firstOrCreate(
            ['title' => 'Livraison Zone A'],
            ['description' => 'Livraison de matériel - Zone A', 'status' => 'planned']
        );
        $m2 = Mission::firstOrCreate(
            ['title' => 'Collecte Zone B'],
            ['description' => 'Collecte de documents - Zone B', 'status' => 'in_progress', 'starts_at' => now()->subHours(2)]
        );
        $m3 = Mission::firstOrCreate(
            ['title' => 'Retour dépôt'],
            ['description' => 'Retour véhicule au dépôt', 'status' => 'completed', 'starts_at' => now()->subDays(1), 'ends_at' => now()->subDays(1)->addHours(3)]
        );

        $m1->users()->syncWithoutDetaching([$chauffeur->id]);
        $m2->users()->syncWithoutDetaching([$chauffeur->id]);
        $m3->users()->syncWithoutDetaching([$chauffeur->id]);
    }
}

