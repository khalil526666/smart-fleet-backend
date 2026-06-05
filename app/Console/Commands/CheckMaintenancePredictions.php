<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Models\Vehicle;
use App\Notifications\MaintenancePredictionAlert;
use App\Services\MaintenancePredictionService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class CheckMaintenancePredictions extends Command
{
    protected $signature = 'predictions:check-maintenance
                            {--force : Ignore le délai anti-doublon de 7 jours}';

    protected $description = 'Lance les prédictions de maintenance pour tous les véhicules actifs
                              et crée des notifications pour les cas urgents (eleve / moyen).';

    public function handle(MaintenancePredictionService $service): int
    {
        $force = (bool) $this->option('force');
        $vehicles = Vehicle::where('status', 'active')->get();
        $recipients = User::whereIn('role', ['admin', 'gestionnaire'])->get();

        if ($vehicles->isEmpty()) {
            $this->warn('Aucun véhicule actif trouvé.');
            return self::SUCCESS;
        }

        if ($recipients->isEmpty()) {
            $this->warn('Aucun admin/gestionnaire trouvé — les notifications ne seront pas envoyées.');
        }

        $this->info("Vérification de {$vehicles->count()} véhicule(s) actif(s)...");
        $this->newLine();

        $cntPredictions = 0;
        $cntNotified    = 0;
        $cntSkipped     = 0;
        $cntFailed      = 0;

        foreach ($vehicles as $vehicle) {
            $label = "{$vehicle->brand} {$vehicle->model} ({$vehicle->license_plate})";

            $prediction = $service->predictAndStore($vehicle);

            if ($prediction === null) {
                $this->error("  ✗ [{$vehicle->id}] {$label} — API ML injoignable");
                $cntFailed++;
                continue;
            }

            $cntPredictions++;
            $urgency = $prediction->urgency_level;
            $days    = $prediction->predicted_days;

            if (! in_array($urgency, ['eleve', 'moyen'], true)) {
                $this->line("  · [{$vehicle->id}] {$label} — {$days} jours (<fg=green>faible</>)");
                continue;
            }

            // Anti-doublon : une notification par véhicule max tous les 7 jours
            if (! $force && $this->recentNotificationExists($vehicle->id)) {
                $this->warn("  ~ [{$vehicle->id}] {$label} — {$days} jours (<fg=yellow>{$urgency}</>) ignorée (notification récente)");
                $cntSkipped++;
                continue;
            }

            // Charger le véhicule sur la prédiction pour la notification
            $prediction->load('vehicle');

            foreach ($recipients as $user) {
                $user->notify(new MaintenancePredictionAlert($prediction));
            }

            $color = $urgency === 'eleve' ? 'red' : 'yellow';
            $this->line("  ! [{$vehicle->id}] {$label} — {$days} jours (<fg={$color}>{$urgency}</>) → {$recipients->count()} notification(s) créée(s)");
            $cntNotified++;
        }

        $this->newLine();
        $this->info('─────────────────────────────────────────');
        $this->info("Véhicules traités    : {$vehicles->count()}");
        $this->info("Prédictions réussies : {$cntPredictions}");

        if ($cntFailed > 0) {
            $this->error("Échecs API ML        : {$cntFailed}");
        }

        $this->info("Notifications créées : {$cntNotified}");

        if ($cntSkipped > 0) {
            $this->warn("Ignorées (anti-doublon) : {$cntSkipped}");
        }

        $this->info('─────────────────────────────────────────');

        return self::SUCCESS;
    }

    private function recentNotificationExists(int $vehicleId): bool
    {
        return DB::table('notifications')
            ->where('type', MaintenancePredictionAlert::class)
            ->where('created_at', '>', now()->subDays(7))
            ->whereRaw("JSON_EXTRACT(data, '$.vehicle_id') = ?", [$vehicleId])
            ->exists();
    }
}
