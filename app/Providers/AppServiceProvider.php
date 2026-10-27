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
        // le super administrateur de la plateforme a tous les droits
        \Illuminate\Support\Facades\Gate::before(fn ($user) => $user->est_super_admin ? true : null);
    }
}
