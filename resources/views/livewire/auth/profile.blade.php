<div class="mx-auto max-w-4xl">
    @php($input = 'w-full rounded-[10px] border border-slate-300 bg-slate-50 px-3 py-2.5 text-slate-900 focus:border-[#0F2A26] focus:bg-white focus:outline-none focus:ring-4 focus:ring-[#0F2A26]/10')

    <div class="mb-6">
        <h1 class="text-2xl font-bold tracking-tight text-slate-900">Mi perfil</h1>
        <p class="text-sm text-slate-500">Tus datos de acceso a veteFlow.</p>
    </div>

    <div class="grid gap-6 md:grid-cols-2">
        <form wire:submit="updateProfile" class="space-y-4 rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
            <h2 class="text-lg font-semibold text-slate-900">Datos personales</h2>

            <div>
                <label for="name" class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500">Nombre</label>
                <input id="name" type="text" wire:model="name" autocomplete="name" class="{{ $input }}">
                @error('name') <p class="mt-1 text-sm text-rose-600">{{ $message }}</p> @enderror
            </div>

            <div>
                <label for="email" class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500">Email</label>
                <input id="email" type="email" wire:model="email" autocomplete="username" class="{{ $input }}">
                @error('email') <p class="mt-1 text-sm text-rose-600">{{ $message }}</p> @enderror
            </div>

            <button type="submit" wire:loading.attr="disabled" wire:target="updateProfile" class="rounded-[10px] bg-[#0F2A26] px-4 py-2.5 font-bold text-white shadow-sm hover:bg-[#1A3D37] disabled:opacity-50">
                Guardar datos
            </button>
        </form>

        <form wire:submit="updatePassword" class="space-y-4 rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
            <h2 class="text-lg font-semibold text-slate-900">Cambiar contraseña</h2>

            <div>
                <label for="current_password" class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500">Contraseña actual</label>
                <input id="current_password" type="password" wire:model="current_password" autocomplete="current-password" class="{{ $input }}">
                @error('current_password') <p class="mt-1 text-sm text-rose-600">{{ $message }}</p> @enderror
            </div>

            <div>
                <label for="password" class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500">Contraseña nueva</label>
                <input id="password" type="password" wire:model="password" autocomplete="new-password" class="{{ $input }}">
                @error('password') <p class="mt-1 text-sm text-rose-600">{{ $message }}</p> @enderror
            </div>

            <div>
                <label for="password_confirmation" class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500">Repetir contraseña nueva</label>
                <input id="password_confirmation" type="password" wire:model="password_confirmation" autocomplete="new-password" class="{{ $input }}">
            </div>

            <button type="submit" wire:loading.attr="disabled" wire:target="updatePassword" class="rounded-[10px] bg-[#0F2A26] px-4 py-2.5 font-bold text-white shadow-sm hover:bg-[#1A3D37] disabled:opacity-50">
                Cambiar contraseña
            </button>
        </form>
    </div>
</div>
