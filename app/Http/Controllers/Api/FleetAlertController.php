<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\FleetAlert;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FleetAlertController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        if (! in_array($request->user()->role, ['admin', 'gestionnaire'], true)) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $rows = FleetAlert::query()
            ->with('mission:id,title')
            ->orderByDesc('id')
            ->limit(100)
            ->get()
            ->map(fn (FleetAlert $a) => [
                'id' => $a->id,
                'type' => $a->type,
                'message' => $a->message,
                'meta' => $a->meta,
                'mission_id' => $a->mission_id,
                'mission_title' => $a->mission?->title,
                'created_at' => $a->created_at->toIso8601String(),
            ]);

        return response()->json(['data' => $rows]);
    }
}
