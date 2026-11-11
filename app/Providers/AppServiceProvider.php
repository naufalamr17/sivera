<?php

namespace App\Providers;

use App\Models\User;
use App\Services\Sivera\DisplayTime;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->scoped(DisplayTime::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Gate::define('admin', fn (User $user): bool => $user->is_active && $user->role === 'admin');
        RateLimiter::for('sivera-ingress', fn (Request $request): Limit => Limit::perMinute(600)->by($request->ip()));
    }
}
