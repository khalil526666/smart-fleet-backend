<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Driver;
use App\Models\FuelRecord;
use App\Models\Maintenance;
use App\Models\Mission;
use App\Models\Vehicle;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    private const CACHE_KEY = 'dashboard:v3';

    private const CACHE_TTL_SECONDS = 30;

    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        if (! in_array($user->role, ['admin', 'gestionnaire'], true)) {
            return response()->json($this->limitedPayload());
        }

        $payload = Cache::remember(self::CACHE_KEY, now()->addSeconds(self::CACHE_TTL_SECONDS), function () {
            return $this->buildFleetPayload();
        });

        return response()->json($payload);
    }

    /**
     * Same JSON shape as the fleet dashboard; avoids exposing aggregates to non-fleet roles.
     */
    private function limitedPayload(): array
    {
        return [
            'total_vehicles' => 0,
            'active_vehicles' => 0,
            'vehicles_under_maintenance' => 0,
            'missions_today' => 0,
            'active_drivers' => 0,
            'fuel' => [
                'total_liters' => 0.0,
                'total_cost' => 0.0,
            ],
            'series' => [
                'vehicles_by_status' => [],
                'fuel_by_month' => [],
                'maintenance_by_month' => [],
                'missions_by_month' => [],
            ],
            'recent_maintenances' => [],
            'meta' => ['scope' => 'limited', 'reason' => 'fleet_roles_only'],
        ];
    }

    private function buildFleetPayload(): array
    {
        $totalVehicles = Vehicle::count();
        $vehiclesUnderMaintenance = Vehicle::where('status', 'maintenance')->count();
        $activeVehicles = Vehicle::where('status', 'active')->count();
        $activeDrivers = Driver::whereNotNull('vehicle_id')->count();

        $today = now()->toDateString();
        $missionsToday = Mission::query()
            ->where(function ($q) use ($today) {
                $q->whereDate('starts_at', $today)
                    ->orWhere(function ($q2) use ($today) {
                        $q2->whereNull('starts_at')->whereDate('created_at', $today);
                    });
            })
            ->count();

        $ymMissionExpr = $this->sqlYearMonth('COALESCE(starts_at, created_at)');
        $sinceMission = now()->subMonths(6)->startOfMonth()->toDateTimeString();

        $missionsByMonth = Mission::query()
            ->selectRaw("{$ymMissionExpr} as ym, COUNT(*) as count")
            ->whereRaw('COALESCE(starts_at, created_at) >= ?', [$sinceMission])
            ->groupBy(DB::raw($ymMissionExpr))
            ->orderBy(DB::raw($ymMissionExpr))
            ->get()
            ->map(fn ($r) => [
                'ym' => (string) $r->ym,
                'count' => (int) ($r->count ?? 0),
            ])
            ->values();

        $fuelTotals = FuelRecord::query()->selectRaw('SUM(liters) as total_liters, SUM(price) as total_cost')->first();

        $recentMaintenances = Maintenance::with('vehicle')
            ->orderByDesc('date')
            ->limit(10)
            ->get();

        $vehiclesByStatus = Vehicle::query()
            ->select('status', DB::raw('COUNT(*) as count'))
            ->groupBy('status')
            ->orderBy('status')
            ->get()
            ->map(fn ($r) => ['status' => (string) $r->status, 'count' => (int) $r->count])
            ->values();

        $ymFuelExpr = $this->sqlYearMonth('date');
        $sinceFuel = now()->subMonths(6)->startOfMonth()->toDateString();

        $fuelByMonth = FuelRecord::query()
            ->selectRaw("{$ymFuelExpr} as ym, SUM(liters) as liters, SUM(price) as cost")
            ->where('date', '>=', $sinceFuel)
            ->groupBy(DB::raw($ymFuelExpr))
            ->orderBy(DB::raw($ymFuelExpr))
            ->get()
            ->map(fn ($r) => [
                'ym' => (string) $r->ym,
                'liters' => (float) ($r->liters ?? 0),
                'cost' => (float) ($r->cost ?? 0),
            ])
            ->values();

        $ymMaintExpr = $this->sqlYearMonth('date');
        $sinceMaint = now()->subMonths(6)->startOfMonth()->toDateString();

        $maintenanceByMonth = Maintenance::query()
            ->selectRaw("{$ymMaintExpr} as ym, COUNT(*) as count, SUM(cost) as cost")
            ->where('date', '>=', $sinceMaint)
            ->groupBy(DB::raw($ymMaintExpr))
            ->orderBy(DB::raw($ymMaintExpr))
            ->get()
            ->map(fn ($r) => [
                'ym' => (string) $r->ym,
                'count' => (int) ($r->count ?? 0),
                'cost' => (float) ($r->cost ?? 0),
            ])
            ->values();

        return [
            'total_vehicles' => $totalVehicles,
            'active_vehicles' => $activeVehicles,
            'vehicles_under_maintenance' => $vehiclesUnderMaintenance,
            'missions_today' => $missionsToday,
            'active_drivers' => $activeDrivers,
            'fuel' => [
                'total_liters' => (float) ($fuelTotals->total_liters ?? 0),
                'total_cost' => (float) ($fuelTotals->total_cost ?? 0),
            ],
            'series' => [
                'vehicles_by_status' => $vehiclesByStatus,
                'fuel_by_month' => $fuelByMonth,
                'maintenance_by_month' => $maintenanceByMonth,
                'missions_by_month' => $missionsByMonth,
            ],
            'recent_maintenances' => $recentMaintenances,
        ];
    }

    /**
     * SQL fragment for "YYYY-MM" from a datetime/date column or expression (driver-specific).
     */
    private function sqlYearMonth(string $columnOrExpression): string
    {
        $driver = DB::connection()->getDriverName();

        return match ($driver) {
            'mysql', 'mariadb' => "DATE_FORMAT({$columnOrExpression}, '%Y-%m')",
            'pgsql' => "to_char(({$columnOrExpression})::timestamp, 'YYYY-MM')",
            'sqlite' => "strftime('%Y-%m', {$columnOrExpression})",
            default => "DATE_FORMAT({$columnOrExpression}, '%Y-%m')",
        };
    }
}
