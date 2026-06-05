<?php

namespace App\Services;

use App\Models\FleetAlert;
use App\Models\Mission;
use App\Models\Trajet;

/**
 * Rule checks after each trajet point (overspeed, idle, delayed mission).
 */
class FleetRulesService
{
    /** Approximate implied speed above this (km/h) raises overspeed. */
    private const SPEED_LIMIT_KMH = 110;

    /** No movement beyond this distance (m) with long gap => idle. */
    private const IDLE_RADIUS_M = 40;

    /** Gap between fixes longer than this while almost stationary => idle (seconds). */
    private const IDLE_TIME_SEC = 900;

    public function evaluate(Mission $mission, Trajet $current, ?Trajet $previous): void
    {
        $this->checkDelayedMission($mission);
        if ($previous) {
            $this->checkOverspeed($mission, $current, $previous);
            $this->checkIdle($mission, $current, $previous);
        }
    }

    private function checkDelayedMission(Mission $mission): void
    {
        if ($mission->status !== 'in_progress' || ! $mission->ends_at) {
            return;
        }
        if (now()->lte($mission->ends_at)) {
            return;
        }
        if ($this->recentAlertExists($mission->id, 'delayed_mission')) {
            return;
        }
        $this->createAlert($mission, 'delayed_mission', 'Mission en retard : l’heure de fin prévue est dépassée.', [
            'ends_at' => $mission->ends_at->toIso8601String(),
        ]);
    }

    private function checkOverspeed(Mission $mission, Trajet $current, Trajet $previous): void
    {
        $dt = $previous->recorded_at && $current->recorded_at
            ? abs($previous->recorded_at->diffInSeconds($current->recorded_at))
            : 0;
        if ($dt < 2) {
            return;
        }
        $dist = $this->haversineM(
            (float) $previous->lat,
            (float) $previous->lng,
            (float) $current->lat,
            (float) $current->lng
        );
        $kmh = ($dist / $dt) * 3.6;
        if ($kmh <= self::SPEED_LIMIT_KMH) {
            return;
        }
        if ($this->recentAlertExists($mission->id, 'overspeed')) {
            return;
        }
        $this->createAlert($mission, 'overspeed', 'Excès de vitesse détecté (~'.(int) round($kmh).' km/h).', [
            'kmh' => round($kmh, 1),
            'segment_m' => round($dist, 1),
            'dt_sec' => $dt,
        ]);
    }

    private function checkIdle(Mission $mission, Trajet $current, Trajet $previous): void
    {
        $dt = $previous->recorded_at && $current->recorded_at
            ? abs($previous->recorded_at->diffInSeconds($current->recorded_at))
            : 0;
        if ($dt < self::IDLE_TIME_SEC) {
            return;
        }
        $dist = $this->haversineM(
            (float) $previous->lat,
            (float) $previous->lng,
            (float) $current->lat,
            (float) $current->lng
        );
        if ($dist > self::IDLE_RADIUS_M) {
            return;
        }
        if ($this->recentAlertExists($mission->id, 'vehicle_idle')) {
            return;
        }
        $this->createAlert($mission, 'vehicle_idle', 'Véhicule à l’arrêt prolongé pendant une mission active.', [
            'idle_minutes' => round($dt / 60),
        ]);
    }

    private function recentAlertExists(int $missionId, string $type): bool
    {
        return FleetAlert::query()
            ->where('mission_id', $missionId)
            ->where('type', $type)
            ->where('created_at', '>', now()->subMinutes(45))
            ->exists();
    }

    private function createAlert(Mission $mission, string $type, string $message, array $meta): void
    {
        FleetAlert::create([
            'mission_id' => $mission->id,
            'type' => $type,
            'message' => $message,
            'meta' => $meta,
        ]);

        app(PusherService::class)->publishFleetAlert([
            'mission_id' => $mission->id,
            'type' => $type,
            'message' => $message,
            'meta' => $meta,
            'created_at' => now()->toIso8601String(),
        ]);
    }

    private function haversineM(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $R = 6371000;
        $toRad = fn (float $d) => $d * M_PI / 180;
        $dLat = $toRad($lat2 - $lat1);
        $dLng = $toRad($lng2 - $lng1);
        $a = sin($dLat / 2) ** 2 + cos($toRad($lat1)) * cos($toRad($lat2)) * sin($dLng / 2) ** 2;

        return 2 * $R * asin(min(1, sqrt($a)));
    }
}
