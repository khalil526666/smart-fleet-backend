<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Route;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        apiPrefix: 'api',
        health: '/up',
        then: function () {
            Route::get('/', function () {
                return response()->json([
                    'name' => config('app.name'),
                    'message' => 'Smart Fleet API is running. Use the Angular app at http://localhost:4200',
                    'docs' => '/api/login (POST), /api/vehicles, /api/dashboard, etc.',
                ]);
            });
        },
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->statefulApi();
        // API-only app: don't redirect unauthenticated users to a "login" route.
        // Return 401 JSON instead (prevents "Route [login] not defined." 500s).
        $middleware->redirectGuestsTo(fn () => null);
        $middleware->alias([
            'role' => \App\Http\Middleware\EnsureUserRole::class,
            'isAdmin' => \App\Http\Middleware\IsAdmin::class,
        ]);
    })
    ->withSchedule(function (Schedule $schedule) {
        $schedule->command('predictions:check-maintenance')->dailyAt('06:00');
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();
