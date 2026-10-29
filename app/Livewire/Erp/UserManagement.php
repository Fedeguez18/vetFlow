<?php

namespace App\Livewire\Erp;

use App\Exceptions\CashSessionException;
use App\Exceptions\UserAccountException;
use App\Livewire\Concerns\RequiresAdmin;
use App\Models\PosTerminal;
use App\Models\User;
use App\Services\PosTerminalService;
use App\Services\UserService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts::erp')]
#[Title('Usuarios')]
class UserManagement extends Component
{
    use RequiresAdmin;

    #[Locked]
    public ?int $userId = null;

    public string $name = '';

    public string $email = '';

    public string $role = User::ROLE_SELLER;

    public string $password = '';

    /**
     * POS code just generated: shown once, only its hash is stored.
     *
     * @var array{name: string, code: string}|null
     */
    public ?array $newCode = null;

    /**
     * Sellers only use the POS with their code; administrators also log in to the ERP with email and password.
     */
    public function saveUser(UserService $users): void
    {
        $this->name = trim($this->name);
        $this->email = Str::lower(trim($this->email));
        $editing = $this->userId ? User::findOrFail($this->userId) : null;
        $isAdmin = $this->role === User::ROLE_ADMIN;

        $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'role' => ['required', Rule::in(array_keys(User::ROLES))],
            'email' => [$isAdmin ? 'required' : 'nullable', 'email', 'max:255', Rule::unique('users', 'email')->ignore($this->userId)],
            'password' => [$isAdmin && ! $editing?->password ? 'required' : 'nullable', 'string', Password::defaults()],
        ], [
            'name.required' => 'Ingresá el nombre.',
            'email.required' => 'Ingresá el email.',
            'email.email' => 'El email no es válido.',
            'email.unique' => 'Ya existe un usuario con ese email.',
            'role.*' => 'Elegí un rol válido.',
            'password.required' => 'Ingresá una contraseña.',
            'password.min' => 'La contraseña debe tener al menos :min caracteres.',
        ]);

        $attributes = ['name' => $this->name, 'role' => $this->role];

        if ($isAdmin) {
            $attributes += ['email' => $this->email, 'password' => $this->password];
        }

        try {
            $user = $users->save($attributes, Auth::user(), $editing);
        } catch (UserAccountException $e) {
            $this->addError('role', $e->getMessage());

            return;
        }

        $this->cancelUser();
        unset($this->users);

        if ($editing) {
            $this->dispatch('toast', message: "Usuario {$user->name} actualizado.");

            return;
        }

        $this->newCode = ['name' => $user->name, 'code' => $users->generatePosCode($user)];
        $this->dispatch('toast', message: "Usuario {$user->name} creado.");
    }

    /**
     * Replace the user's POS code (forgotten or exposed) and show the new one.
     */
    public function regeneratePosCode(int $id, UserService $users): void
    {
        $user = User::findOrFail($id);

        $this->newCode = ['name' => $user->name, 'code' => $users->generatePosCode($user)];
        unset($this->users);
    }

    public function dismissNewCode(): void
    {
        $this->newCode = null;
    }

    public function revokeTerminal(int $id, PosTerminalService $terminals): void
    {
        $terminal = PosTerminal::findOrFail($id);

        try {
            $terminals->revoke($terminal);
        } catch (CashSessionException $e) {
            $this->dispatch('toast', message: $e->getMessage(), type: 'error');

            return;
        }

        unset($this->terminals);
        $this->dispatch('toast', message: "Terminal {$terminal->name} deshabilitada.");
    }

    public function editUser(int $id): void
    {
        $user = User::findOrFail($id);

        $this->resetValidation();
        $this->userId = $user->id;
        $this->name = $user->name;
        $this->email = (string) $user->email;
        $this->role = array_key_exists($user->role, User::ROLES) ? $user->role : User::ROLE_SELLER;
        $this->password = '';
    }

    public function cancelUser(): void
    {
        $this->reset('userId', 'name', 'email', 'role', 'password');
        $this->resetValidation();
    }

    public function deactivateUser(int $id, UserService $users): void
    {
        $user = User::findOrFail($id);

        try {
            $users->deactivate($user, Auth::user());
        } catch (UserAccountException $e) {
            $this->dispatch('toast', message: $e->getMessage(), type: 'error');

            return;
        }

        if ($this->userId === $id) {
            $this->cancelUser();
        }

        unset($this->users);
        $this->dispatch('toast', message: "Usuario {$user->name} dado de baja.");
    }

    public function reactivateUser(int $id, UserService $users): void
    {
        $user = User::findOrFail($id);
        $users->reactivate($user);

        unset($this->users);
        $this->dispatch('toast', message: "Usuario {$user->name} reactivado.");
    }

    /**
     * @return Collection<int, User>
     */
    #[Computed]
    public function users(): Collection
    {
        return User::query()->orderByDesc('is_active')->orderBy('name')->get();
    }

    /**
     * @return Collection<int, PosTerminal>
     */
    #[Computed]
    public function terminals(): Collection
    {
        return PosTerminal::query()->with(['enabledBy', 'openCashSession.user'])->orderBy('name')->get();
    }

    public function render()
    {
        return view('livewire.erp.user-management', [
            'roles' => User::ROLES,
        ]);
    }
}
