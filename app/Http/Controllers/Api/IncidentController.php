<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Incident;
use App\Models\Mission;
use App\Models\User;
use App\Notifications\IncidentReportedNotification;
use App\Services\EmailService;
use App\Services\PusherService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class IncidentController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $q = Incident::query()
            ->with(['mission:id,title,status', 'user:id,name,email,role'])
            ->orderByDesc('id');

        if ($user->role === 'chauffeur') {
            $q->where('user_id', $user->id);
        }

        return response()->json(['data' => $q->get()]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'mission_id' => 'nullable|integer|exists:missions,id',
            'type' => 'nullable|string|max:255',
            'description' => 'required|string|max:5000',
        ]);

        $user = $request->user();

        $missionId = $validated['mission_id'] ?? null;
        if ($missionId) {
            $mission = Mission::query()->findOrFail($missionId);
            if ($user->role === 'chauffeur' && ! $mission->users()->where('users.id', $user->id)->exists()) {
                return response()->json(['message' => 'Forbidden'], 403);
            }
        }

        $incident = Incident::create([
            'mission_id' => $missionId,
            'user_id' => $user?->id,
            'type' => $validated['type'] ?? null,
            'description' => $validated['description'],
            'status' => 'open',
        ]);

        if ($user && $user->role === 'chauffeur') {
            $targets = $this->notifyAdminsAndManagers(new IncidentReportedNotification($incident, $user));
            foreach ($targets as $t) {
                try {
                    app(EmailService::class)->sendIncidentReported($t, $incident, $user);
                } catch (\Throwable $e) {
                    // logged by EmailService
                }
            }

            $incident->load('mission:id,title,status');
            try {
                app(EmailService::class)->sendIncidentReportConfirmationToChauffeur($user, $incident);
            } catch (\Throwable $e) {
                // logged by EmailService
            }
        }

        return response()->json(['data' => $incident], 201);
    }

    private function notifyAdminsAndManagers(\Illuminate\Notifications\Notification $notification)
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
}

