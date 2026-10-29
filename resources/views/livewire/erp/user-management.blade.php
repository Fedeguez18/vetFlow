@use('App\Models\User')

<div class="space-y-6">
    <div>
        <h1 class="text-2xl font-bold tracking-tight text-slate-900">Usuarios</h1>
        <p class="text-sm text-slate-500">Cada usuario abre la caja del mostrador con su código. Los administradores además entran al ERP con email y contraseña.</p>
    </div>

    @if ($newCode)
        <div class="flex flex-wrap items-center justify-between gap-4 rounded-2xl border border-emerald-200 bg-emerald-50 px-5 py-4">
            <div>
                <div class="text-sm font-semibold text-emerald-900">Código de {{ $newCode['name'] }} para el mostrador</div>
                <div class="mt-1 font-mono text-3xl font-bold tracking-[0.3em] text-emerald-900">{{ $newCode['code'] }}</div>
                <p class="mt-1 text-xs text-emerald-800">Anotalo y pasáselo: no se vuelve a mostrar. Si se lo olvida, generá uno nuevo.</p>
            </div>
            <button type="button" wire:click="dismissNewCode" class="rounded-[10px] px-3 py-2 text-sm font-semibold text-emerald-900 ring-1 ring-emerald-300 hover:bg-white">Listo, ya lo anoté</button>
        </div>
    @endif

    <section class="rounded-2xl border border-slate-200 bg-white shadow-sm">
        <div class="flex items-center justify-between border-b border-slate-200 px-5 py-4">
            <h2 class="text-lg font-semibold text-slate-900">Cuentas</h2>
            <span class="rounded-full bg-slate-100 px-2.5 py-0.5 text-xs font-semibold text-slate-600">{{ $this->users->count() }}</span>
        </div>

        @php($isAdminForm = $role === User::ROLE_ADMIN)
        <form wire:submit="saveUser" class="grid gap-3 border-b border-slate-200 bg-slate-50/60 px-5 py-4 sm:grid-cols-2 xl:grid-cols-[minmax(0,1fr)_10rem_minmax(0,1fr)_minmax(0,1fr)_auto] xl:items-start">
            <div>
                <x-form.label for="userName">{{ $userId ? 'Editar usuario · Nombre' : 'Nuevo usuario · Nombre' }}</x-form.label>
                <x-form.input id="userName" type="text" wire:model="name" placeholder="Ej.: Laura Gómez" class="text-sm" />
                <x-form.error name="name" />
            </div>

            <div>
                <x-form.label for="userRole">Rol</x-form.label>
                <x-form.select id="userRole" wire:model.live="role" class="text-sm">
                    @foreach ($roles as $key => $roleLabel)
                        <option value="{{ $key }}">{{ $roleLabel }}</option>
                    @endforeach
                </x-form.select>
                <x-form.error name="role" />
            </div>

            @if ($isAdminForm)
                <div>
                    <x-form.label for="userEmail">Email (para el ERP)</x-form.label>
                    <x-form.input id="userEmail" type="email" wire:model="email" placeholder="diego@veterinaria.com" autocomplete="off" class="text-sm" />
                    <x-form.error name="email" />
                </div>

                <div>
                    <x-form.label for="userPassword">{{ $userId ? 'Contraseña nueva' : 'Contraseña (para el ERP)' }}</x-form.label>
                    <x-form.input id="userPassword" type="password" wire:model="password" autocomplete="new-password" placeholder="{{ $userId ? 'Vacía para no cambiarla' : 'Mínimo 8 caracteres' }}" class="text-sm" />
                    <x-form.error name="password" />
                </div>
            @else
                <p class="text-sm text-slate-500 sm:col-span-2 xl:pt-7">El vendedor solo usa el mostrador: {{ $userId ? 'entra con su código.' : 'al crearlo se genera su código.' }}</p>
            @endif

            {{-- Top padding = label height, so the buttons line up with the inputs even when an error grows a column. --}}
            <div class="flex gap-2 xl:pt-5">
                <button type="submit" wire:loading.attr="disabled" wire:target="saveUser" class="whitespace-nowrap rounded-[10px] bg-[#0F2A26] px-4 py-2.5 text-sm font-bold text-white shadow-sm hover:bg-[#1A3D37] disabled:opacity-50">
                    {{ $userId ? 'Guardar' : 'Agregar usuario' }}
                </button>
                @if ($userId)
                    <button type="button" wire:click="cancelUser" class="whitespace-nowrap rounded-[10px] px-3 py-2.5 text-sm font-semibold text-slate-600 ring-1 ring-slate-300 hover:bg-white">Cancelar</button>
                @endif
            </div>
        </form>

        <ul class="divide-y divide-slate-100">
            @foreach ($this->users as $user)
                @php($isMe = $user->id === auth()->id())
                <li wire:key="user-{{ $user->id }}" @class(['flex flex-wrap items-center justify-between gap-3 px-5 py-3', 'bg-emerald-50/50' => $userId === $user->id, 'opacity-60' => ! $user->is_active])>
                    <div class="min-w-0">
                        <div class="flex flex-wrap items-center gap-2">
                            <span class="font-semibold text-slate-900">{{ $user->name }}</span>
                            @if ($isMe)
                                <span class="text-xs font-medium text-slate-400">(vos)</span>
                            @endif
                            <span @class([
                                'rounded-full px-2 py-0.5 text-[11px] font-semibold uppercase tracking-wide ring-1',
                                'bg-sky-50 text-sky-700 ring-sky-600/15' => $user->role === User::ROLE_ADMIN,
                                'bg-emerald-50 text-emerald-700 ring-emerald-600/15' => $user->role !== User::ROLE_ADMIN,
                            ])>{{ $user->role }}</span>
                            @unless ($user->is_active)
                                <span class="rounded-full bg-slate-100 px-2 py-0.5 text-[11px] font-semibold uppercase tracking-wide text-slate-500">Dado de baja</span>
                            @endunless
                            @unless ($user->hasPosCode())
                                <span class="rounded-full bg-amber-50 px-2 py-0.5 text-[11px] font-semibold uppercase tracking-wide text-amber-700 ring-1 ring-amber-600/15">Sin código</span>
                            @endunless
                        </div>
                        <div class="mt-0.5 text-xs text-slate-500">{{ $user->email ?? 'Solo mostrador' }}</div>
                    </div>
                    <div class="flex shrink-0 gap-1">
                        @if ($user->is_active)
                            <button
                                type="button"
                                wire:click="regeneratePosCode({{ $user->id }})"
                                @if ($user->hasPosCode()) wire:confirm="¿Generar un código nuevo para {{ $user->name }}? El actual deja de funcionar." @endif
                                class="rounded-lg px-2.5 py-1.5 text-xs font-semibold text-slate-600 hover:bg-slate-100 hover:text-slate-900"
                            >{{ $user->hasPosCode() ? 'Nuevo código' : 'Generar código' }}</button>
                        @endif
                        <button type="button" wire:click="editUser({{ $user->id }})" class="rounded-lg px-2.5 py-1.5 text-xs font-semibold text-slate-600 hover:bg-slate-100 hover:text-slate-900">Editar</button>
                        @if ($user->is_active)
                            @unless ($isMe)
                                <button type="button" wire:click="deactivateUser({{ $user->id }})" wire:confirm="¿Dar de baja a {{ $user->name }}? No va a poder ingresar más." class="rounded-lg px-2.5 py-1.5 text-xs font-semibold text-rose-600 hover:bg-rose-50">Dar de baja</button>
                            @endunless
                        @else
                            <button type="button" wire:click="reactivateUser({{ $user->id }})" class="rounded-lg px-2.5 py-1.5 text-xs font-semibold text-emerald-700 hover:bg-emerald-50">Reactivar</button>
                        @endif
                    </div>
                </li>
            @endforeach
        </ul>
    </section>

    <section class="rounded-2xl border border-slate-200 bg-white shadow-sm">
        <div class="border-b border-slate-200 px-5 py-4">
            <h2 class="text-lg font-semibold text-slate-900">Terminales de mostrador</h2>
            <p class="text-sm text-slate-500">Para habilitar una PC, abrí el POS en esa PC con tu usuario de administrador.</p>
        </div>

        <ul class="divide-y divide-slate-100">
            @forelse ($this->terminals as $terminal)
                <li wire:key="terminal-{{ $terminal->id }}" class="flex flex-wrap items-center justify-between gap-3 px-5 py-3">
                    <div class="min-w-0">
                        <div class="flex flex-wrap items-center gap-2">
                            <span class="font-semibold text-slate-900">{{ $terminal->name }}</span>
                            @if ($terminal->openCashSession)
                                <span class="rounded-full bg-emerald-50 px-2 py-0.5 text-[11px] font-semibold uppercase tracking-wide text-emerald-700 ring-1 ring-emerald-600/15">Caja abierta: {{ $terminal->openCashSession->user->name }}</span>
                            @else
                                <span class="rounded-full bg-slate-100 px-2 py-0.5 text-[11px] font-semibold uppercase tracking-wide text-slate-500">Caja cerrada</span>
                            @endif
                        </div>
                        <div class="mt-0.5 text-xs text-slate-500">
                            Habilitada el {{ $terminal->created_at->format('d/m/Y') }}{{ $terminal->enabledBy ? ' por '.$terminal->enabledBy->name : '' }}
                        </div>
                    </div>
                    <button type="button" wire:click="revokeTerminal({{ $terminal->id }})" wire:confirm="¿Deshabilitar la terminal {{ $terminal->name }}? Esa PC deja de abrir el mostrador." class="rounded-lg px-2.5 py-1.5 text-xs font-semibold text-rose-600 hover:bg-rose-50">Deshabilitar</button>
                </li>
            @empty
                <li class="px-5 py-8 text-center text-sm text-slate-500">Todavía no hay terminales habilitadas.</li>
            @endforelse
        </ul>
    </section>
</div>
