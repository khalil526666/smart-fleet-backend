<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Feature tests act as pure API clients (Bearer token only).
        //
        // Sanctum's Guard::__invoke() first loops over config('sanctum.guard', 'web').
        // When any guard in that list has a cached session user, Sanctum returns the user
        // with a TransientToken *before* checking the Bearer header. Session state
        // persists across requests within a single PHPUnit test, causing spurious 200s
        // after token revocation and making currentAccessToken() return TransientToken.
        //
        // Setting sanctum.guard to [] makes the loop a no-op, so Sanctum goes directly
        // to Bearer token lookup — matching how a real API client behaves.
        config(['sanctum.guard' => []]);
    }

    /**
     * Override call() to clear Sanctum's per-request user cache before every HTTP call.
     *
     * Within a single PHPUnit test, all requests share one application instance.
     * Sanctum's Guard caches the resolved user in $this->user after the first successful
     * authentication. Without this reset, requests made after token revocation still see
     * the stale cached user and return 200 instead of 401.
     */
    public function call($method, $uri, $parameters = [], $cookies = [], $files = [], $server = [], $content = null)
    {
        if (isset($this->app)) {
            $this->app['auth']->forgetGuards();
        }

        return parent::call($method, $uri, $parameters, $cookies, $files, $server, $content);
    }
}
