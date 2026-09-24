<?php

namespace App\Providers;

use App\Contracts\LocalSecretStore;
use App\Support\WindowsDpapiSecretStore;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(LocalSecretStore::class, WindowsDpapiSecretStore::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
