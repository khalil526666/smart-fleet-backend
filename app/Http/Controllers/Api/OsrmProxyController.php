<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

/**
 * Proxies OSRM route requests so the SPA can call same-origin /api and avoid
 * CORS issues with some browsers / networks. Uses the same OSRM API as Leaflet Routing Machine.
 */
class OsrmProxyController extends Controller
{
    public function drivingRoute(Request $request): JsonResponse
    {
        $coordinates = $request->query('coordinates');
        if (! is_string($coordinates) || $coordinates === '') {
            return response()->json(['message' => 'Missing coordinates query (lng,lat;lng,lat).'], 422);
        }

        $pairs = explode(';', $coordinates);
        if (count($pairs) < 2) {
            return response()->json(['message' => 'At least two coordinate pairs (start and end) are required.'], 422);
        }

        foreach ($pairs as $pair) {
            $xy = explode(',', $pair);
            if (count($xy) !== 2 || ! is_numeric($xy[0]) || ! is_numeric($xy[1])) {
                return response()->json(['message' => 'Invalid coordinates format.'], 422);
            }
        }

        $base = config('osrm.base_url');
        $url = "{$base}/route/v1/driving/{$coordinates}";

        $withSteps = $request->boolean('steps');

        try {
            $response = Http::timeout(20)->acceptJson()->get($url, [
                'overview' => 'full',
                'geometries' => 'geojson',
                'steps' => $withSteps ? 'true' : 'false',
                'continue_straight' => 'true',
            ]);
        } catch (\Throwable $e) {
            return response()->json(['message' => 'Routing service unavailable.'], 503);
        }

        return response()->json($response->json(), $response->status());
    }
}
