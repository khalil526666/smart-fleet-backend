<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Mission;
use App\Models\Trajet;
use App\Models\User;
use App\Notifications\MissionCompletedNotification;
use App\Notifications\MissionStartedNotification;
use App\Services\EmailService;
use App\Services\FleetRulesService;
use App\Services\PusherService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Notifications\Notification;

class MissionController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $q = Mission::query()->withCount(['trajets', 'incidents'])->orderByDesc('id');

        // Chauffeurs don't manage missions globally; they see only assigned ones.
        if ($user->role === 'chauffeur') {
            $q->whereHas('users', fn ($u) => $u->where('users.id', $user->id));
        }

        return response()->json(['data' => $q->get()]);
    }

    public function show(Request $request, Mission $mission): JsonResponse
    {
        $user = $request->user();

        if ($user->role === 'chauffeur' && ! $mission->users()->where('users.id', $user->id)->exists()) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $mission->load(['vehicle', 'users:id,name,email,role', 'trajets', 'incidents']);
        return response()->json(['data' => $mission]);
    }

    public function store(Request $request): JsonResponse
    {
        $user = $request->user();
        $isManager = in_array($user->role, ['admin', 'gestionnaire'], true);
        $isChauffeur = $user->role === 'chauffeur';

        if (! $isManager && ! $isChauffeur) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $validated = $request->validate([
            'vehicle_id' => 'nullable|integer|exists:vehicles,id',
            'chauffeur_id' => 'nullable|integer|exists:users,id',
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'starts_at' => 'nullable|date',
            'ends_at' => 'nullable|date|after_or_equal:starts_at',
        ]);

        // Chauffeur : mission pour lui-même uniquement (pas de véhicule via API — liste véhicules réservée gestionnaires)
        if ($isChauffeur) {
            $mission = Mission::create([
                'vehicle_id' => null,
                'title' => $validated['title'],
                'description' => $validated['description'] ?? null,
                'status' => 'planned',
                'starts_at' => $validated['starts_at'] ?? null,
                'ends_at' => $validated['ends_at'] ?? null,
            ]);
            $mission->users()->sync([$user->id]);

            return response()->json(['data' => $mission->load(['vehicle', 'users:id,name,email,role'])], 201);
        }

        $mission = Mission::create([
            'vehicle_id' => $validated['vehicle_id'] ?? null,
            'title' => $validated['title'],
            'description' => $validated['description'] ?? null,
            'status' => 'planned',
            'starts_at' => $validated['starts_at'] ?? null,
            'ends_at' => $validated['ends_at'] ?? null,
        ]);

        if (! empty($validated['chauffeur_id'])) {
            $chauffeur = User::query()->findOrFail($validated['chauffeur_id']);
            if ($chauffeur->role !== 'chauffeur') {
                return response()->json(['message' => 'Selected user is not a chauffeur.'], 422);
            }
            $mission->users()->sync([$chauffeur->id]);
            $mission->load('vehicle');
            try {
                app(EmailService::class)->sendMissionAssignedToChauffeur($chauffeur, $mission);
            } catch (\Throwable $e) {
                // SMTP errors are logged in EmailService
            }
        }

        return response()->json(['data' => $mission->load(['vehicle', 'users:id,name,email,role'])], 201);
    }

    public function start(Request $request, Mission $mission): JsonResponse
    {
        $user = $request->user();
        if ($user->role !== 'chauffeur' || ! $mission->users()->where('users.id', $user->id)->exists()) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        if ($mission->status === 'completed') {
            return response()->json(['message' => 'Mission already completed.'], 422);
        }

        $mission->update([
            'status' => 'in_progress',
            'starts_at' => $mission->starts_at ?? now(),
        ]);

        $targets = $this->notifyAdminsAndManagers(new MissionStartedNotification($mission, $user));
        $this->emailAdminsAndManagers($targets, fn (User $t) => app(EmailService::class)->sendMissionStarted($t, $mission, $user));

        try {
            app(EmailService::class)->sendMissionStartedNoticeToChauffeur($user, $mission);
        } catch (\Throwable $e) {
            // journalisé dans EmailService
        }

        return response()->json(['data' => $mission->fresh()]);
    }

    public function complete(Request $request, Mission $mission): JsonResponse
    {
        $user = $request->user();
        if ($user->role !== 'chauffeur' || ! $mission->users()->where('users.id', $user->id)->exists()) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $mission->update([
            'status' => 'completed',
            'ends_at' => now(),
        ]);

        $targets = $this->notifyAdminsAndManagers(new MissionCompletedNotification($mission, $user));
        $this->emailAdminsAndManagers($targets, fn (User $t) => app(EmailService::class)->sendMissionCompleted($t, $mission, $user));

        try {
            app(EmailService::class)->sendMissionCompletedNoticeToChauffeur($user, $mission);
        } catch (\Throwable $e) {
            // journalisé dans EmailService
        }

        return response()->json(['data' => $mission->fresh()]);
    }

    private function notifyAdminsAndManagers(Notification $notification)
    {
        $targets = User::query()
            ->whereIn('role', ['admin', 'gestionnaire'])
            ->get();

        $pusher = app(PusherService::class);
        foreach ($targets as $t) {
            $t->notify($notification);
            $payload = [
                'data' => $notification->toArray($t),
                'unread_count' => $t->unreadNotifications()->count(),
            ];
            $pusher->notifyUser($t->id, $payload);
        }

        return $targets;
    }

    private function emailAdminsAndManagers($targets, \Closure $sender): void
    {
        // Sending synchronously for now (simple setup). Can be queued later.
        foreach ($targets as $t) {
            try {
                $sender($t);
            } catch (\Throwable $e) {
                // Email failures are logged by EmailService; notifications (DB/Pusher) still work.
            }
        }
    }

    public function chauffeurs(Request $request): JsonResponse
    {
        // Admin/gestionnaire can list chauffeurs for assignment
        if (! in_array($request->user()->role, ['admin', 'gestionnaire'], true)) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        return response()->json([
            'data' => User::query()
                ->select(['id', 'name', 'email', 'role'])
                ->where('role', 'chauffeur')
                ->orderBy('name')
                ->get(),
        ]);
    }

    public function addTrajetPoint(Request $request, Mission $mission): JsonResponse
    {
        $user = $request->user();
        if ($user->role !== 'chauffeur' || ! $mission->users()->where('users.id', $user->id)->exists()) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $validated = $request->validate([
            'lat' => 'required|numeric|between:-90,90',
            'lng' => 'required|numeric|between:-180,180',
            'recorded_at' => 'nullable|date',
        ]);

        $previous = Trajet::query()
            ->where('mission_id', $mission->id)
            ->orderByDesc('recorded_at')
            ->orderByDesc('id')
            ->first();

        $p = Trajet::create([
            'mission_id' => $mission->id,
            'lat' => $validated['lat'],
            'lng' => $validated['lng'],
            'recorded_at' => $validated['recorded_at'] ?? now(),
        ]);

        app(FleetRulesService::class)->evaluate($mission->fresh(), $p, $previous);

        $pusher = app(PusherService::class);
        $pusher->publishMissionTrajet($mission->id, [
            'mission_id' => $mission->id,
            'trajet' => [
                'id' => $p->id,
                'lat' => (float) $p->lat,
                'lng' => (float) $p->lng,
                'recorded_at' => $p->recorded_at?->toIso8601String(),
            ],
        ]);

        return response()->json(['data' => $p], 201);
    }

    /**
     * Trip analytics from recorded trajet points (distance, duration, average speed).
     */
    public function tripReport(Request $request, Mission $mission): JsonResponse
    {
        $user = $request->user();

        if ($user->role === 'chauffeur' && ! $mission->users()->where('users.id', $user->id)->exists()) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        if (! in_array($user->role, ['admin', 'gestionnaire', 'chauffeur'], true)) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $points = $mission->trajets()->orderBy('recorded_at')->orderBy('id')->get();
        if ($points->isEmpty()) {
            return response()->json([
                'data' => [
                    'distance_m' => 0,
                    'duration_sec' => 0,
                    'avg_speed_kmh' => null,
                    'point_count' => 0,
                    'started_at' => null,
                    'ended_at' => null,
                ],
            ]);
        }

        $distanceM = 0.0;
        $prev = $points->first();
        foreach ($points->slice(1) as $cur) {
            $distanceM += $this->haversineM(
                (float) $prev->lat,
                (float) $prev->lng,
                (float) $cur->lat,
                (float) $cur->lng
            );
            $prev = $cur;
        }

        $first = $points->first();
        $last = $points->last();
        $t0 = $first->recorded_at ?? $first->created_at;
        $t1 = $last->recorded_at ?? $last->created_at;
        $durationSec = $t0 && $t1 ? abs($t0->diffInSeconds($t1)) : 0;

        $avgKmh = ($durationSec > 2 && $distanceM > 0)
            ? round(($distanceM / $durationSec) * 3.6, 1)
            : null;

        return response()->json([
            'data' => [
                'distance_m' => round($distanceM, 1),
                'duration_sec' => $durationSec,
                'avg_speed_kmh' => $avgKmh,
                'point_count' => $points->count(),
                'started_at' => $t0?->toIso8601String(),
                'ended_at' => $t1?->toIso8601String(),
            ],
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

