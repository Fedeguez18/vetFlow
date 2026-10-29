<?php

namespace App\Providers;

use App\Http\Middleware\EnsurePosTerminal;
use App\Http\Middleware\EnsureUserIsActive;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;

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
        Gate::define('admin', fn (User $user): bool => $user->isAdmin());

        // Re-run it on Livewire requests too, so a baja also stops actions on an already open page.
        Livewire::addPersistentMiddleware([EnsureUserIsActive::class, EnsurePosTerminal::class]);
    }
}
