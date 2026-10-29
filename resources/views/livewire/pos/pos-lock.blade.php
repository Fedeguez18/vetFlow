<div class="w-full max-w-sm">
    <div class="mb-6 flex items-center justify-center gap-2.5">
        <x-logo-mark class="size-10" />
        <span class="text-2xl font-bold tracking-tight text-slate-900">vete<span class="text-emerald-600">Flow</span></span>
    </div>

    @if ($this->user)
        <form wire:submit="openCashRegister" class="space-y-4 rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
            <div>
                <h1 class="text-lg font-semibold text-slate-900">Hola, {{ $this->user->name }}</h1>
                <p class="text-sm text-slate-500">Vas a abrir la caja a tu nombre. Todo lo que se venda hasta cerrarla queda registrado a tu nombre.</p>
            </div>

            <div>
                <x-form.label for="openingAmount">Fondo inicial (efectivo para cambio)</x-form.label>
                <x-form.input id="openingAmount" type="text" inputmode="decimal" wire:model="openingAmount" placeholder="0" autofocus autocomplete="off" />
                <x-form.error name="openingAmount" />
            </div>

            <button type="submit" wire:loading.attr="disabled" class="w-full rounded-[10px] bg-[#0F2A26] px-4 py-3 font-bold text-white shadow-sm hover:bg-[#1A3D37] disabled:opacity-50">
                Abrir caja
            </button>

            <button type="button" wire:click="back" class="w-full text-sm font-semibold text-slate-500 hover:text-slate-800">No soy yo</button>
        </form>
    @else
        <form wire:submit="unlock" class="space-y-4 rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
            <div>
                <h1 class="text-lg font-semibold text-slate-900">Caja cerrada</h1>
                <p class="text-sm text-slate-500">Ingresá tu código para abrir la caja.</p>
            </div>

            @if (session('status'))
                <p class="rounded-[10px] bg-emerald-50 px-3 py-2.5 text-sm text-emerald-800 ring-1 ring-emerald-600/15">{{ session('status') }}</p>
            @endif

            <div>
                <x-form.label for="code">Código</x-form.label>
                <x-form.input id="code" type="password" inputmode="numeric" maxlength="6" wire:model="code" autofocus autocomplete="off" class="text-center font-mono text-2xl tracking-[0.5em]" />
                <x-form.error name="code" />
            </div>

            <button type="submit" wire:loading.attr="disabled" class="w-full rounded-[10px] bg-[#0F2A26] px-4 py-3 font-bold text-white shadow-sm hover:bg-[#1A3D37] disabled:opacity-50">
                Continuar
            </button>
        </form>
    @endif
</div>
