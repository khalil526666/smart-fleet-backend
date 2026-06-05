<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\FuelRecord;
use App\Models\Maintenance;
use App\Models\Vehicle;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;

class AlertController extends Controller
{
    public function index(): JsonResponse
    {
        $payload = Cache::remember('alerts:v1', now()->addSeconds(30), function () {
            $vehicles = Vehicle::query()
                ->select(['id', 'brand', 'model', 'license_plate', 'mileage', 'status'])
                ->orderBy('id')
                ->get();

            $alerts = [];

            foreach ($vehicles as $v) {
                $alerts = array_merge($alerts, $this->maintenanceAlertsForVehicle($v));
                $alerts = array_merge($alerts, $this->fuelAlertsForVehicle($v));
            }

            usort($alerts, function (array $a, array $b) {
                $prio = ['critical' => 0, 'medium' => 1, 'info' => 2];
                $pa = $prio[$a['level']] ?? 99;
                $pb = $prio[$b['level']] ?? 99;
                if ($pa !== $pb) return $pa <=> $pb;
                return strcmp($b['created_at'], $a['created_at']);
            });

            return [
                'count' => count($alerts),
                'alerts' => $alerts,
                'rules' => [
                    'maintenance' => [
                        'interval_days' => 180,
                        'interval_km' => 10000,
                        'critical_overdue_days' => 30,
                        'critical_overdue_km' => 2000,
                    ],
                    'fuel_anomaly' => [
                        'threshold_ratio' => 1.30,
                        'critical_ratio' => 1.60,
                        'min_segments' => 3,
                    ],
                ],
            ];
        });

        return response()->json($payload);
    }

    private function maintenanceAlertsForVehicle(Vehicle $v): array
    {
        $intervalDays = 180;
        $intervalKm = 10000;

        $last = Maintenance::query()
            ->where('vehicle_id', $v->id)
            ->orderByDesc('date')
            ->first(['id', 'date', 'odometer', 'maintenance_type']);

        if (!$last) {
            return [[
                'type' => 'maintenance',
                'level' => 'medium',
                'vehicle' => $this->vehiclePayload($v),
                'title' => 'No maintenance history',
                'message' => 'No maintenance records found. Add the last service to enable due-date predictions.',
                'created_at' => now()->toISOString(),
                'meta' => [
                    'reason' => 'missing_history',
                ],
            ]];
        }

        $dueByDate = $last->date->copy()->addDays($intervalDays);
        $daysOverdue = now()->startOfDay()->diffInDays($dueByDate->startOfDay(), false); // negative = overdue
        $isOverdueByDate = $daysOverdue < 0;

        $kmOverdue = null;
        $isOverdueByKm = false;
        $dueKm = null;

        if (!is_null($last->odometer)) {
            $dueKm = (int) $last->odometer + $intervalKm;
            $kmOverdue = (int) $v->mileage - $dueKm;
            $isOverdueByKm = $kmOverdue >= 0;
        }

        if (!$isOverdueByDate && !$isOverdueByKm) {
            return [];
        }

        $critical = false;
        if ($isOverdueByDate && abs($daysOverdue) >= 30) $critical = true;
        if ($isOverdueByKm && !is_null($kmOverdue) && $kmOverdue >= 2000) $critical = true;

        $level = $critical ? 'critical' : 'medium';
        $reasons = [];
        if ($isOverdueByDate) $reasons[] = 'date';
        if ($isOverdueByKm) $reasons[] = 'mileage';

        $messageParts = [];
        if ($isOverdueByDate) {
            $messageParts[] = 'Overdue by ' . abs($daysOverdue) . ' day(s) since ' . $dueByDate->toDateString();
        }
        if ($isOverdueByKm && !is_null($kmOverdue) && !is_null($dueKm)) {
            $messageParts[] = 'Overdue by ' . $kmOverdue . ' km (due at ' . $dueKm . ' km)';
        }

        return [[
            'type' => 'maintenance_due',
            'level' => $level,
            'vehicle' => $this->vehiclePayload($v),
            'title' => 'Maintenance due',
            'message' => implode(' • ', $messageParts),
            'created_at' => now()->toISOString(),
            'meta' => [
                'last_maintenance' => [
                    'id' => $last->id,
                    'date' => $last->date->toDateString(),
                    'odometer' => $last->odometer,
                    'type' => $last->maintenance_type,
                ],
                'due_by_date' => $dueByDate->toDateString(),
                'days_overdue' => $isOverdueByDate ? abs($daysOverdue) : 0,
                'due_km' => $dueKm,
                'km_overdue' => $isOverdueByKm ? $kmOverdue : 0,
                'reasons' => $reasons,
            ],
        ]];
    }

    private function fuelAlertsForVehicle(Vehicle $v): array
    {
        // Need odometer to compute consumption between fills
        $records = FuelRecord::query()
            ->where('vehicle_id', $v->id)
            ->whereNotNull('odometer')
            ->orderBy('date')
            ->get(['id', 'date', 'liters', 'odometer']);

        if ($records->count() < 4) {
            return [];
        }

        $segments = [];
        for ($i = 1; $i < $records->count(); $i++) {
            $prev = $records[$i - 1];
            $cur = $records[$i];
            $deltaKm = (int) $cur->odometer - (int) $prev->odometer;
            if ($deltaKm <= 0) continue;
            $liters = (float) $cur->liters;
            $lPer100 = ($liters / $deltaKm) * 100.0;
            $segments[] = [
                'from_id' => $prev->id,
                'to_id' => $cur->id,
                'date' => $cur->date->toDateString(),
                'delta_km' => $deltaKm,
                'liters' => $liters,
                'l_per_100km' => $lPer100,
            ];
        }

        if (count($segments) < 3) {
            return [];
        }

        $latest = $segments[count($segments) - 1];
        $baselineSegments = array_slice($segments, 0, -1);
        $avg = array_sum(array_map(fn ($s) => $s['l_per_100km'], $baselineSegments)) / max(count($baselineSegments), 1);
        if ($avg <= 0) return [];

        $ratio = $latest['l_per_100km'] / $avg;
        if ($ratio < 1.30) {
            return [];
        }

        $level = $ratio >= 1.60 ? 'critical' : 'medium';

        return [[
            'type' => 'fuel_anomaly',
            'level' => $level,
            'vehicle' => $this->vehiclePayload($v),
            'title' => 'Fuel consumption anomaly',
            'message' => sprintf(
                'Latest: %.2f L/100km over %d km — Avg: %.2f L/100km (%.0f%% higher)',
                $latest['l_per_100km'],
                $latest['delta_km'],
                $avg,
                ($ratio - 1) * 100
            ),
            'created_at' => now()->toISOString(),
            'meta' => [
                'avg_l_per_100km' => $avg,
                'latest' => $latest,
                'ratio' => $ratio,
                'threshold_ratio' => 1.30,
                'critical_ratio' => 1.60,
            ],
        ]];
    }

    private function vehiclePayload(Vehicle $v): array
    {
        return [
            'id' => $v->id,
            'brand' => $v->brand,
            'model' => $v->model,
            'license_plate' => $v->license_plate,
            'mileage' => (int) $v->mileage,
            'status' => $v->status,
        ];
    }
}

