<?php

namespace App\Providers;

use App\Models\User;
use Illuminate\Support\Facades\Gate;
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
        // Super‑Admin: grant all abilities when the user has `is_superadmin` flag.
        Gate::before(function (?User $user, $ability) {
            if ($user?->is_superadmin) {
                return true;
            }

            return null;
        });
    }
}
