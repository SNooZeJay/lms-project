<?php

namespace App\Providers;

use App\Contracts\LocalSecretStore;
use App\Models\ActivityLog;
use App\Models\User;
use App\Policies\ActivityLogPolicy;
use App\Policies\UserPolicy;
use App\Support\WindowsDpapiSecretStore;
use Illuminate\Support\Facades\Gate;
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
        Gate::policy(User::class, UserPolicy::class);
        Gate::policy(ActivityLog::class, ActivityLogPolicy::class);
    }
}
