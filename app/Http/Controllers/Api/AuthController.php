<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use App\Services\EmailService;

class AuthController extends Controller
{
    public function register(Request $request): JsonResponse
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users,email',
            'password' => ['required', 'confirmed', Password::defaults()],
        ], [
            'name.required' => 'The name field is required.',
            'email.required' => 'The email field is required.',
            'email.unique' => 'This email is already registered.',
            'password.required' => 'The password field is required.',
            'password.confirmed' => 'The password confirmation does not match.',
        ]);

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            // Security: never allow self-assigning privileged roles during registration.
            'role' => 'user',
        ]);

        $token = $user->createToken('auth')->plainTextToken;

        return response()->json([
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role,
                'mail_delivery' => config('mail.real_inbox') ? 'live' : 'sandbox',
            ],
            'token' => $token,
            'token_type' => 'Bearer',
        ], 201);
    }

    public function login(Request $request): JsonResponse
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);

        if (! Auth::attempt($request->only('email', 'password'))) {
            throw ValidationException::withMessages([
                'email' => ['The provided credentials are incorrect.'],
            ]);
        }

        $user = Auth::user();
        $user->tokens()->delete();
        $token = $user->createToken('auth')->plainTextToken;

        return response()->json([
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role,
                'mail_delivery' => config('mail.real_inbox') ? 'live' : 'sandbox',
            ],
            'token' => $token,
            'token_type' => 'Bearer',
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Logged out']);
    }

    public function user(Request $request): JsonResponse
    {
        $user = $request->user();

        return response()->json([
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'role' => $user->role,
            'photo_url' => $user->photo_url,
            'phone' => $user->phone,
            'cin_number' => $user->cin_number,
            'cin_photo_recto_url' => $user->cin_photo_recto_url,
            'cin_photo_verso_url' => $user->cin_photo_verso_url,
            'date_naissance' => $user->date_naissance,
            'adresse' => $user->adresse,
            'created_at' => $user->created_at,
            'mail_delivery' => config('mail.real_inbox') ? 'live' : 'sandbox',
        ]);
    }

    public function updateProfile(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'phone' => 'nullable|string|max:20',
            'cin_number' => 'nullable|string|max:20',
            'date_naissance' => 'nullable|date',
            'adresse' => 'nullable|string|max:1000',
        ]);

        $user = $request->user();
        $user->update($validated);
        $user->refresh();

        return response()->json([
            'message' => 'Profile updated.',
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role,
                'photo_url' => $user->photo_url,
                'phone' => $user->phone,
                'cin_number' => $user->cin_number,
                'cin_photo_recto_url' => $user->cin_photo_recto_url,
                'cin_photo_verso_url' => $user->cin_photo_verso_url,
                'date_naissance' => $user->date_naissance,
                'adresse' => $user->adresse,
                'created_at' => $user->created_at,
                'mail_delivery' => config('mail.real_inbox') ? 'live' : 'sandbox',
            ],
        ]);
    }

    public function uploadCinPhoto(Request $request): JsonResponse
    {
        $request->validate([
            'photo' => 'required|image|max:4096',
            'side' => 'required|in:recto,verso',
        ]);

        $user = $request->user();
        $side = $request->input('side');
        $field = "cin_photo_{$side}";

        if ($user->$field) {
            Storage::disk('public')->delete($user->$field);
        }

        $path = $request->file('photo')->store("cin/{$side}", 'public');
        $user->update([$field => $path]);
        $user->refresh();

        $urlKey = "cin_photo_{$side}_url";

        return response()->json([
            'message' => 'CIN photo updated.',
            $urlKey => $user->$urlKey,
        ]);
    }

    public function changePassword(Request $request): JsonResponse
    {
        $request->validate([
            'current_password' => 'required',
            'password' => ['required', 'confirmed', Password::defaults()],
        ], [
            'password.confirmed' => 'The password confirmation does not match.',
        ]);

        $user = $request->user();

        if (! Hash::check($request->current_password, $user->password)) {
            throw ValidationException::withMessages([
                'current_password' => ['The current password is incorrect.'],
            ]);
        }

        $user->update(['password' => Hash::make($request->password)]);

        return response()->json(['message' => 'Password changed successfully.']);
    }

    public function updatePhoto(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'photo' => 'required|image|max:4096',
        ]);

        $user = $request->user();
        if ($user->photo_path) {
            Storage::disk('public')->delete($user->photo_path);
        }

        $path = $request->file('photo')->store('users', 'public');
        $user->update(['photo_path' => $path]);

        return response()->json([
            'message' => 'Photo updated',
            'photo_url' => $user->fresh()->photo_url,
        ]);
    }

    public function deletePhoto(Request $request): JsonResponse
    {
        $user = $request->user();
        if ($user->photo_path) {
            Storage::disk('public')->delete($user->photo_path);
        }
        $user->update(['photo_path' => null]);

        return response()->json([
            'message' => 'Photo deleted',
            'photo_url' => null,
        ]);
    }

    public function forgotPassword(Request $request): JsonResponse
    {
        $request->validate([
            'email' => 'required|email',
        ]);

        $user = User::where('email', $request->email)->first();

        // Always return a generic message (avoid user enumeration).
        // If SMTP is not configured, frontend will get a clear error (APP_DEBUG can help during setup).
        if (! $user) {
            return response()->json(['message' => 'If that email exists, we have sent a reset link.']);
        }

        $token = Str::random(64);
        $frontendUrl = rtrim(env('FRONTEND_URL', 'http://localhost:4200'), '/');
        $resetUrl = "{$frontendUrl}/reset-password?token=" . urlencode($token) . '&email=' . urlencode($user->email);

        DB::table('password_reset_tokens')->updateOrInsert(
            ['email' => $user->email],
            ['token' => Hash::make($token), 'created_at' => now()]
        );

        try {
            app(EmailService::class)->sendPasswordReset($user, $resetUrl);
        } catch (\Throwable $e) {
            return response()->json([
                'message' => 'Failed to send reset email. Check SMTP configuration.',
                'error' => app()->hasDebugModeEnabled() ? $e->getMessage() : null,
            ], 500);
        }

        $payload = ['message' => 'If that email exists, we have sent a reset link.'];
        if (app()->hasDebugModeEnabled()) {
            $payload['reset_url'] = $resetUrl; // helpful for local testing
        }
        return response()->json($payload);
    }

    public function resetPassword(Request $request): JsonResponse
    {
        $request->validate([
            'token' => 'required|string',
            'email' => 'required|email',
            'password' => ['required', 'confirmed', Password::defaults()],
        ], [
            'password.confirmed' => 'The password confirmation does not match.',
        ]);

        $record = DB::table('password_reset_tokens')->where('email', $request->email)->first();

        if (! $record || ! Hash::check($request->token, $record->token)) {
            throw ValidationException::withMessages([
                'email' => ['This reset token is invalid or has expired.'],
            ]);
        }

        $expiry = 60; // minutes
        if (now()->diffInMinutes(\Carbon\Carbon::parse($record->created_at)) > $expiry) {
            DB::table('password_reset_tokens')->where('email', $request->email)->delete();
            throw ValidationException::withMessages([
                'email' => ['This reset token has expired.'],
            ]);
        }

        $user = User::where('email', $request->email)->firstOrFail();
        $user->update(['password' => Hash::make($request->password)]);
        DB::table('password_reset_tokens')->where('email', $request->email)->delete();

        return response()->json(['message' => 'Password has been reset.']);
    }
}
