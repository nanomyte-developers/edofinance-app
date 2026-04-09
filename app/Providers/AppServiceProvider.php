<?php

namespace App\Providers;

use Illuminate\Http\Request;
use App\Services\ActivityLogger;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(ActivityLogger::class, function ($app) {
            return new ActivityLogger($app->make(Request::class));
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
