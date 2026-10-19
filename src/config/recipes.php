<?php

return [

    // Defaults for `php artisan recipes:create-owner`. Read through config() (not env()) so
    // they keep working after `php artisan optimize` caches the configuration.
    'owner' => [
        'name' => env('OWNER_NAME', 'Sarah'),
        'email' => env('OWNER_EMAIL'),
        'password' => env('OWNER_PASSWORD'),
    ],

];
