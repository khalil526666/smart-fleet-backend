<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\FuelRecord;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FuelRecordController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = FuelRecord::with('vehicle');

        if ($request->filled('vehicle_id')) {
            $query->where('vehicle_id', $request->vehicle_id);
        }

        $perPage = (int) $request->integer('per_page', 15);
        $perPage = max(1, min(100, $perPage));

        $records = $query->orderByDesc('date')->paginate($perPage);

        return response()->json($records);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'vehicle_id' => 'required|exists:vehicles,id',
            'liters' => 'required|numeric|min:0',
            'price' => 'required|numeric|min:0',
            'date' => 'required|date',
            'odometer' => 'nullable|integer|min:0',
        ]);

        $record = FuelRecord::create($validated);

        return response()->json($record->load('vehicle'), 201);
    }

    public function show(FuelRecord $fuelRecord): JsonResponse
    {
        $fuelRecord->load('vehicle');
        return response()->json($fuelRecord);
    }

    public function update(Request $request, FuelRecord $fuelRecord): JsonResponse
    {
        $validated = $request->validate([
            'vehicle_id' => 'sometimes|exists:vehicles,id',
            'liters' => 'sometimes|numeric|min:0',
            'price' => 'sometimes|numeric|min:0',
            'date' => 'sometimes|date',
            'odometer' => 'nullable|integer|min:0',
        ]);

        $fuelRecord->update($validated);

        return response()->json($fuelRecord->fresh('vehicle'));
    }

    public function destroy(FuelRecord $fuelRecord): JsonResponse
    {
        $fuelRecord->delete();
        return response()->json(null, 204);
    }
}
