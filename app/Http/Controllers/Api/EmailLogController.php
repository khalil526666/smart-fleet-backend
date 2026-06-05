<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\EmailLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EmailLogController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        if (! in_array($user->role, ['admin', 'gestionnaire'], true)) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $perPage = (int) $request->integer('per_page', 20);
        $perPage = max(1, min(100, $perPage));

        $q = EmailLog::query()->orderByDesc('id');

        if ($request->filled('type')) {
            $q->where('type', $request->string('type'));
        }
        if ($request->filled('status')) {
            $q->where('status', $request->string('status'));
        }
        if ($request->filled('to_email')) {
            $q->where('to_email', 'like', '%' . $request->string('to_email') . '%');
        }

        return response()->json($q->paginate($perPage));
    }
}

