<?php

namespace App\Providers;

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
        if (! class_exists('AuthorizationException', false)) {
            class_alias(\Illuminate\Auth\Access\AuthorizationException::class, 'AuthorizationException');
        }
    }
}
