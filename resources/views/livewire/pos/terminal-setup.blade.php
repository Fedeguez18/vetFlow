<div class="w-full max-w-sm">
    <div class="mb-6 flex items-center justify-center gap-2.5">
        <x-logo-mark class="size-10" />
        <span class="text-2xl font-bold tracking-tight text-slate-900">vete<span class="text-emerald-600">Flow</span></span>
    </div>

    <div class="space-y-4 rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
        <div>
            <h1 class="text-lg font-semibold text-slate-900">Esta PC no es un mostrador</h1>
            <p class="text-sm text-slate-500">El POS solo funciona en las PCs que un administrador habilitó como terminal.</p>
        </div>

        @if ($canEnable)
            <form wire:submit="enable" class="space-y-4">
                <div>
                    <x-form.label for="terminalName">Nombre de la terminal</x-form.label>
                    <x-form.input id="terminalName" type="text" wire:model="name" maxlength="60" />
                    <x-form.error name="name" />
                </div>

                <p class="rounded-[10px] bg-amber-50 px-3 py-2.5 text-sm text-amber-800 ring-1 ring-amber-600/15">
                    Al habilitarla se cierra tu sesión en esta PC y queda pidiendo el código del vendedor. Hacelo solo en la PC del mostrador.
                </p>

                <button type="submit" wire:loading.attr="disabled" class="w-full rounded-[10px] bg-[#0F2A26] px-4 py-3 font-bold text-white shadow-sm hover:bg-[#1A3D37] disabled:opacity-50">
                    Habilitar esta PC como terminal
                </button>
            </form>
        @elseauth
            <p class="text-sm text-slate-600">Estás con la sesión de {{ auth()->user()->name }} ({{ auth()->user()->role }}). Para habilitar esta PC tiene que ingresar un administrador.</p>

            <button type="button" wire:click="switchToAdmin" class="w-full rounded-[10px] bg-[#0F2A26] px-4 py-3 font-bold text-white shadow-sm hover:bg-[#1A3D37]">
                Cerrar esta sesión e ingresar como administrador
            </button>
        @else
            <p class="text-sm text-slate-600">Si esta es la PC del mostrador, un administrador tiene que ingresar para habilitarla.</p>

            <a href="{{ route('login') }}" class="block w-full rounded-[10px] bg-[#0F2A26] px-4 py-3 text-center font-bold text-white shadow-sm hover:bg-[#1A3D37]">
                Ingresar como administrador
            </a>
        @endif
    </div>
</div>
