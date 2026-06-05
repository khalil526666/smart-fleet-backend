<?php

namespace App\Services;

use App\Models\MaintenancePrediction;
use App\Models\Vehicle;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class MaintenancePredictionService
{
    private string $baseUrl;
    private int $timeout;

    public function __construct()
    {
        $this->baseUrl = rtrim(config('services.ml_api.url', 'http://localhost:8001'), '/');
        $this->timeout = (int) config('services.ml_api.timeout', 5);
    }

    public function buildFeatures(Vehicle $vehicle): array
    {
        $ageAnnees = max(1, (int) date('Y') - (int) $vehicle->year);

        $lastOdometer = $vehicle->maintenances()
            ->orderByDesc('date')
            ->value('odometer');

        $kmDepuisDerniereMaint = $lastOdometer !== null
            ? max(0, $vehicle->mileage - $lastOdometer)
            : $vehicle->mileage;

        $nbMaintenances = $vehicle->maintenances()->count();

        $kmParJour = round(
            min(250, max(20, $vehicle->mileage / ($ageAnnees * 365))),
            2
        );

        return [
            'mileage'                   => $vehicle->mileage,
            'km_par_jour'               => $kmParJour,
            'km_depuis_derniere_maint'  => $kmDepuisDerniereMaint,
            'age_vehicule_annees'       => $ageAnnees,
            'nb_maintenances_passees'   => $nbMaintenances,
        ];
    }

    public function predict(Vehicle $vehicle): ?array
    {
        $features = $this->buildFeatures($vehicle);

        try {
            $response = Http::timeout($this->timeout)
                ->post("{$this->baseUrl}/predict", $features);

            if ($response->successful()) {
                return array_merge($response->json(), ['_features' => $features]);
            }

            Log::warning('ML API returned non-2xx response', [
                'vehicle_id' => $vehicle->id,
                'status'     => $response->status(),
                'body'       => $response->body(),
            ]);
        } catch (\Throwable $e) {
            Log::warning('ML API error', [
                'vehicle_id' => $vehicle->id,
                'error'      => $e->getMessage(),
            ]);
        }

        return null;
    }

    public function predictAndStore(Vehicle $vehicle): ?MaintenancePrediction
    {
        $result = $this->predict($vehicle);

        if ($result === null) {
            return null;
        }

        $features = $result['_features'];

        return MaintenancePrediction::create([
            'vehicle_id'        => $vehicle->id,
            'predicted_days'    => (int) $result['jours_avant_maintenance'],
            'predicted_date'    => $result['date_prevue'],
            'urgency_level'     => $result['niveau_urgence'],
            'features_snapshot' => $features,
            'confidence_note'   => $result['confidence_note'] ?? null,
            'model_version'     => '1.0.0',
        ]);
    }
}
