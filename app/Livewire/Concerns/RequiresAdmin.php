<?php

namespace App\Livewire\Concerns;

use Illuminate\Support\Facades\Gate;

/**
 * ERP components: only administrators. Livewire runs the boot hook on every request,
 * so each action (credit notes, deletions, prices, stock) is re-authorized, not just the page load.
 */
trait RequiresAdmin
{
    public function bootRequiresAdmin(): void
    {
        Gate::authorize('admin');
    }
}
