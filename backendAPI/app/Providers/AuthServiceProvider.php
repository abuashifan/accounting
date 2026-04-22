<?php

namespace App\Providers;

use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AuthServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Gate::define('journal.create', fn (User $user): bool => $user->hasPermission('journal.create'));
        Gate::define('journal.update', fn (User $user): bool => $user->hasPermission('journal.update'));
        Gate::define('journal.void', fn (User $user): bool => $user->hasPermission('journal.void'));
        Gate::define('journal.override_period', fn (User $user): bool => $user->hasPermission('journal.override_period'));
        Gate::define('settings.manage', function (User $user): bool {
            if ($user->hasPermission('settings.manage')) {
                return true;
            }

            // Fallback: treat the "admin" role as superuser for app settings.
            return $user->roles()->where('name', 'admin')->exists();
        });
    }
}
