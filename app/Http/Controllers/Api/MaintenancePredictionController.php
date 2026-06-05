<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\MaintenancePrediction;
use App\Models\Vehicle;
use App\Services\MaintenancePredictionService;
use Illuminate\Http\JsonResponse;

class MaintenancePredictionController extends Controller
{
    public function __construct(private MaintenancePredictionService $service) {}

    /**
     * Dernière prédiction pour chaque véhicule actif.
     */
    public function index(): JsonResponse
    {
        $predictions = MaintenancePrediction::query()
            ->with('vehicle')
            ->whereIn('maintenance_predictions.id', function ($sub) {
                $sub->selectRaw('MAX(maintenance_predictions.id)')
                    ->from('maintenance_predictions')
                    ->join('vehicles', 'vehicles.id', '=', 'maintenance_predictions.vehicle_id')
                    ->where('vehicles.status', 'active')
                    ->groupBy('maintenance_predictions.vehicle_id');
            })
            ->orderByDesc('maintenance_predictions.created_at')
            ->get();

        return response()->json(['data' => $predictions]);
    }

    /**
     * Relance la prédiction pour un véhicule et sauvegarde.
     */
    public function refresh(Vehicle $vehicle): JsonResponse
    {
        $prediction = $this->service->predictAndStore($vehicle);

        if ($prediction === null) {
            return response()->json([
                'message' => 'Impossible de contacter l\'API ML. Vérifiez que le service tourne sur '
                    . config('services.ml_api.url') . '.',
            ], 503);
        }

        $prediction->load('vehicle');

        return response()->json(['data' => $prediction], 201);
    }

    /**
     * Historique paginé des prédictions d'un véhicule.
     */
    public function history(Vehicle $vehicle): JsonResponse
    {
        $history = MaintenancePrediction::query()
            ->where('vehicle_id', $vehicle->id)
            ->orderByDesc('created_at')
            ->paginate(20);

        return response()->json($history);
    }
}
