<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Mission;
use App\Services\PusherService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PusherAuthController extends Controller
{
    public function auth(Request $request, PusherService $pusher): JsonResponse
    {
        $user = $request->user();

        if (! $user) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $socketId = $request->input('socket_id');
        $channelName = $request->input('channel_name');

        if (! $socketId || ! $channelName) {
            return response()->json(['message' => 'Invalid auth payload'], 422);
        }

        // Fleet-wide live map / alerts (admin + gestionnaire).
        if ($channelName === 'private-fleet.live') {
            if (! in_array($user->role, ['admin', 'gestionnaire'], true)) {
                return response()->json(['message' => 'Forbidden'], 403);
            }

            return response()->json($pusher->auth($socketId, $channelName));
        }

        // Mission-scoped trajet stream (assigned chauffeur + managers).
        if (str_starts_with($channelName, 'private-mission.')) {
            $missionId = (int) substr($channelName, strlen('private-mission.'));
            $mission = Mission::query()->find($missionId);
            if (! $mission) {
                return response()->json(['message' => 'Forbidden'], 403);
            }

            $allowed = in_array($user->role, ['admin', 'gestionnaire'], true)
                || ($user->role === 'chauffeur' && $mission->users()->where('users.id', $user->id)->exists());

            if (! $allowed) {
                return response()->json(['message' => 'Forbidden'], 403);
            }

            return response()->json($pusher->auth($socketId, $channelName));
        }

        // Notifications inbox (admin + gestionnaire only, own channel).
        if (! in_array($user->role, ['admin', 'gestionnaire'], true)) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $expected = "private-user.{$user->id}";
        if ($channelName !== $expected) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        return response()->json($pusher->auth($socketId, $channelName));
    }
}

