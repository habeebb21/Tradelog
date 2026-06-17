<?php

namespace App\Providers;

use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $appUrl = rtrim((string) config('app.url'), '/');

        if ($appUrl !== '') {
            URL::forceRootUrl($appUrl);

            $scheme = parse_url($appUrl, PHP_URL_SCHEME);
            if (is_string($scheme) && $scheme !== '') {
                URL::forceScheme($scheme);
            }
        }

        // Auto-create SQLite database file if it doesn't exist
        if (config('database.default') === 'sqlite') {
            $database = config('database.connections.sqlite.database');
            if ($database !== ':memory:' && !file_exists($database)) {
                $directory = dirname($database);
                if (!is_dir($directory)) {
                    mkdir($directory, 0755, true);
                }
                touch($database);
                chmod($database, 0664);
            }
        }

        if ($this->app->environment('production') && config('app.debug')) {
            logger()->warning('APP_DEBUG is enabled while APP_ENV=production. Browser and config caching may hide template changes.');
        }
    }
}
