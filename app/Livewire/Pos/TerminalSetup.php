<?php

namespace App\Livewire\Pos;

use App\Services\PosTerminalService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Shown when the mostrador is opened on a PC that isn't a terminal: an administrator enables it.
 */
#[Layout('layouts::guest')]
#[Title('Habilitar terminal')]
class TerminalSetup extends Component
{
    public string $name = 'Mostrador';

    public function mount(PosTerminalService $terminals)
    {
        if ($terminals->current()) {
            return $this->redirectRoute('pos.desk');
        }

        if (Auth::guest()) {
            session()->put('url.intended', route('pos.setup'));
        }
    }

    /**
     * Enable this PC and close the administrator's session on it: from now on it only opens the mostrador.
     */
    public function enable(PosTerminalService $terminals)
    {
        Gate::authorize('admin');

        $this->name = trim($this->name);
        $this->validate(['name' => ['required', 'string', 'max:60']], [
            'name.required' => 'Poné un nombre para la terminal.',
            'name.max' => 'El nombre no puede superar los :max caracteres.',
        ]);

        $terminals->enable($this->name, Auth::user());

        Auth::guard('web')->logout();
        session()->invalidate();
        session()->regenerateToken();

        return $this->redirectRoute('pos.lock');
    }

    /**
     * Someone without permission is logged in on this PC (the login would send them back here):
     * close their session and come back after the administrator logs in.
     */
    public function switchToAdmin()
    {
        Auth::guard('web')->logout();
        session()->invalidate();
        session()->regenerateToken();
        session()->put('url.intended', route('pos.setup'));

        return $this->redirectRoute('login');
    }

    public function render()
    {
        return view('livewire.pos.terminal-setup', [
            'canEnable' => Gate::allows('admin'),
        ]);
    }
}
