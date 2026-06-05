<?php

use App\Http\Controllers\Admin\UserManagementController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'isAdmin'])->group(function () {
    Route::get('/admin/users', [UserManagementController::class, 'index'])->name('admin.users.index');
    Route::post('/users/{id}/make-gestionnaire', [UserManagementController::class, 'makeGestionnaire'])->name('users.makeGestionnaire');
    Route::post('/users/{id}/remove-gestionnaire', [UserManagementController::class, 'removeGestionnaire'])->name('users.removeGestionnaire');
});

