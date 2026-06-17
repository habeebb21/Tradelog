<?php

return [

    'paths' => [
        resource_path('views'),
    ],

    'compiled' => env(
        'VIEW_COMPILED_PATH',
        realpath(storage_path('framework/views'))
    ),

    /*
    | When false, Blade recompiles on every request (recommended for local dev).
    | Defaults to true only in production.
    */
    'cache' => env('VIEW_CACHE', env('APP_ENV', 'production') === 'production'),

    /*
    | When false, compiled views are never invalidated by file timestamps.
    | Keep true in all environments unless you know you need to disable it.
    */
    'check_cache_timestamps' => env('VIEW_CHECK_CACHE_TIMESTAMPS', true),

];
