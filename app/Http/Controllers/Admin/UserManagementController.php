<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class UserManagementController extends Controller
{
    public function index(): View
    {
        $users = User::query()
            ->select(['id', 'name', 'email', 'role', 'created_at'])
            ->orderBy('id')
            ->get();

        return view('admin.users.index', compact('users'));
    }

    public function makeGestionnaire(int $id): RedirectResponse
    {
        $user = User::query()->findOrFail($id);

        if ($user->role !== 'admin') {
            $user->update(['role' => 'gestionnaire']);
        }

        return back();
    }

    public function removeGestionnaire(int $id): RedirectResponse
    {
        $user = User::query()->findOrFail($id);

        if ($user->role !== 'admin') {
            $user->update(['role' => 'user']);
        }

        return back();
    }
}

