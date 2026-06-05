<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UserController extends Controller
{
    public function index(): JsonResponse
    {
        $users = User::query()
            ->select(['id', 'name', 'email', 'role', 'created_at', 'photo_path'])
            ->orderBy('id')
            ->get()
            ->map(fn ($u) => [
                'id' => $u->id,
                'name' => $u->name,
                'email' => $u->email,
                'role' => $u->role,
                'created_at' => $u->created_at,
                'photo_url' => $u->photo_url,
            ]);

        return response()->json(['data' => $users]);
    }

    public function setRole(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'role' => 'required|string|in:user,gestionnaire,chauffeur',
        ]);

        $user = User::query()->findOrFail($id);

        if ($user->role === 'admin') {
            return response()->json(['message' => 'Cannot change admin role.'], 422);
        }

        $user->update(['role' => $validated['role']]);

        return response()->json([
            'message' => 'Role updated.',
            'data' => $user->only(['id', 'name', 'email', 'role']),
        ]);
    }

    public function makeGestionnaire(int $id): JsonResponse
    {
        $user = User::query()->findOrFail($id);

        if ($user->role === 'admin') {
            return response()->json(['message' => 'Cannot change admin role.'], 422);
        }

        $user->update(['role' => 'gestionnaire']);

        return response()->json([
            'message' => 'User promoted to gestionnaire.',
            'data' => $user->only(['id', 'name', 'email', 'role']),
        ]);
    }

    public function removeGestionnaire(int $id): JsonResponse
    {
        $user = User::query()->findOrFail($id);

        if ($user->role === 'admin') {
            return response()->json(['message' => 'Cannot change admin role.'], 422);
        }

        $user->update(['role' => 'user']);

        return response()->json([
            'message' => 'Gestionnaire removed (back to user).',
            'data' => $user->only(['id', 'name', 'email', 'role']),
        ]);
    }

    public function makeChauffeur(int $id): JsonResponse
    {
        $user = User::query()->findOrFail($id);

        if ($user->role === 'admin') {
            return response()->json(['message' => 'Cannot change admin role.'], 422);
        }

        $user->update(['role' => 'chauffeur']);

        return response()->json([
            'message' => 'User assigned as chauffeur.',
            'data' => $user->only(['id', 'name', 'email', 'role']),
        ]);
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $target = User::query()->findOrFail($id);
        $current = $request->user();

        if ($target->role === 'admin') {
            return response()->json(['message' => 'Cannot delete an admin user.'], 422);
        }

        if ($current && (int) $current->id === (int) $target->id) {
            return response()->json(['message' => 'You cannot delete your own account.'], 422);
        }

        $target->tokens()->delete();
        $target->delete();

        return response()->json(['message' => 'User deleted.']);
    }
}

