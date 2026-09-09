<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
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
        // Keyed per project (not per IP): a project's SDK instances may share
        // or rotate IPs, but every event carries the project id in the URL.
        RateLimiter::for('fault-ingest', fn (Request $request) => Limit::perMinute(300)->by($request->route('projectId')));
    }
}
