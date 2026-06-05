<?php

return [
    /*
    |--------------------------------------------------------------------------
    | OSRM public API base (no trailing slash)
    |--------------------------------------------------------------------------
    | Default: Project OSRM demo server. For production, run your own OSRM or
    | use a provider; update OSRM_BASE_URL in .env.
    */
    'base_url' => rtrim(env('OSRM_BASE_URL', 'https://router.project-osrm.org'), '/'),
];
