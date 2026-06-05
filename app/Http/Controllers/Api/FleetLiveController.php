<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Mission;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FleetLiveController extends Controller
{
    /**
     * Active missions with last known position (admin / gestionnaire live map).
     */
    public function index(Request $request): JsonResponse
    {
        if (! in_array($request->user()->role, ['admin', 'gestionnaire'], true)) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $missions = Mission::query()
            ->where('status', 'in_progress')
            ->with([
                'vehicle:id,brand,model,license_plate',
                'users:id,name,role',
                'latestTrajet',
            ])
            ->withCount('trajets')
            ->orderByDesc('id')
            ->get();

        $data = $missions->map(function (Mission $m) {
            $driver = $m->users->firstWhere('role', 'chauffeur') ?? $m->users->first();
            $lt = $m->latestTrajet;

            return [
                'mission_id' => $m->id,
                'title' => $m->title,
                'status' => $m->status,
                'starts_at' => $m->starts_at?->toIso8601String(),
                'ends_at' => $m->ends_at?->toIso8601String(),
                'vehicle' => $m->vehicle ? [
                    'license_plate' => $m->vehicle->license_plate,
                    'label' => trim($m->vehicle->brand.' '.$m->vehicle->model),
                ] : null,
                'driver' => $driver ? ['id' => $driver->id, 'name' => $driver->name] : null,
                'position' => $lt ? [
                    'lat' => (float) $lt->lat,
                    'lng' => (float) $lt->lng,
                    'recorded_at' => $lt->recorded_at?->toIso8601String(),
                ] : null,
                'points_count' => $m->trajets_count,
            ];
        });

        return response()->json(['data' => $data]);
    }
}
