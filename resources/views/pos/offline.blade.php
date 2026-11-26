<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title>Mostrador sin conexión · veteFlow</title>

    @vite(['resources/css/app.css', 'resources/js/pos-offline.js'])
    @livewireStyles
</head>
{{-- Offline mostrador: the browser saves this page and sells from its own copy of the data (resources/js/pos-offline.js). --}}
<body data-pos-page="offline" class="min-h-full bg-[#F4F6F5] font-sans text-slate-800 antialiased">
<div x-data="posOffline" x-cloak class="flex min-h-screen flex-col">
    <header class="sticky top-0 z-40 border-b border-slate-200 bg-white">
        <div class="flex min-h-14 w-full items-center justify-between gap-4 px-4 py-2 sm:px-6">
            <div class="flex items-center gap-3">
                <span class="flex items-center gap-2">
                    <x-logo-mark class="size-8" />
                    <span class="text-lg font-bold tracking-tight text-slate-900">vete<span class="text-emerald-600">Flow</span></span>
                </span>
                <span class="inline-flex items-center gap-1.5 rounded-full bg-amber-50 px-2.5 py-1 text-[11px] font-semibold uppercase tracking-wide text-amber-800 ring-1 ring-amber-600/20">
                    <span class="inline-flex size-2 rounded-full bg-amber-500"></span>
                    Mostrador sin conexión
                </span>
            </div>
            <div class="flex items-center gap-4">
                <span x-show="queueCount > 0" class="rounded-full bg-slate-100 px-2.5 py-1 text-xs font-semibold text-slate-700 ring-1 ring-slate-300">
                    <span x-text="queueCount"></span> <span x-text="queueCount === 1 ? 'venta por subir' : 'ventas por subir'"></span>
                </span>
                <template x-if="snapshot?.cash_session">
                    <div class="hidden text-right leading-tight sm:block">
                        <div class="text-[10px] font-semibold uppercase tracking-wide text-slate-400">Caja abierta</div>
                        <div class="text-sm font-semibold text-slate-900" x-text="snapshot.cash_session.user_name"></div>
                    </div>
                </template>
            </div>
        </div>
    </header>

    {{-- Connection status --}}
    <div
        class="border-b px-4 py-2.5 text-sm font-medium sm:px-6"
        x-bind:class="{
            'border-amber-200 bg-amber-50 text-amber-900': syncState === 'idle',
            'border-sky-200 bg-sky-50 text-sky-900': syncState === 'syncing',
            'border-emerald-200 bg-emerald-50 text-emerald-900': syncState === 'done',
            'border-rose-200 bg-rose-50 text-rose-800': syncState === 'error',
        }"
    >
        <div class="flex flex-wrap items-center gap-3">
            <span class="flex-1" x-show="syncState === 'idle'">
                Sin internet. Las ventas se guardan en esta PC y se suben solas a la caja y la auditoría cuando vuelva la conexión.
                <span class="text-amber-700" x-show="snapshot?.generated_at">Precios y stock al <span x-text="snapshot && new Date(snapshot.generated_at).toLocaleString('es-AR', { day: '2-digit', month: '2-digit', hour: '2-digit', minute: '2-digit' })"></span>.</span>
            </span>
            <span class="flex-1" x-show="syncState !== 'idle'" x-text="syncMessage"></span>
            <button type="button" x-show="syncState === 'done'" x-on:click="backToDesk()" class="rounded-lg bg-[#0F2A26] px-3 py-1.5 text-xs font-bold text-white hover:bg-[#1A3D37]">
                Volver al mostrador normal
            </button>
        </div>
    </div>

    {{-- No data saved in this PC --}}
    <main x-show="state === 'empty' || state === 'nocaja' || state === 'cantunlock'" class="flex flex-1 items-center justify-center p-6">
        <div class="max-w-md space-y-2 rounded-2xl border border-slate-200 bg-white p-6 text-sm text-slate-600 shadow-sm">
            <h1 class="text-lg font-semibold text-slate-900">No se puede vender sin conexión</h1>
            <p x-show="state === 'empty'">Esta PC no tiene guardados los datos del mostrador. Se guardan solos la primera vez que se abre el mostrador con internet.</p>
            <p x-show="state === 'nocaja'">No había una caja abierta cuando se cortó internet. Para vender sin conexión, la caja tiene que abrirse antes, con internet.</p>
            <p x-show="state === 'cantunlock'">El mostrador quedó bloqueado y en esta PC no está guardado el código de <span class="font-semibold" x-text="snapshot?.cash_session?.user_name"></span> para desbloquearlo sin internet.</p>
            <p class="text-slate-500">Cuando vuelva internet, esta pantalla lo detecta sola.</p>
        </div>
    </main>

    {{-- Locked: the caja owner unlocks it with their code, checked in this PC --}}
    <main x-show="state === 'locked'" class="flex flex-1 items-center justify-center p-6">
        <form x-on:submit.prevent="unlock()" class="w-full max-w-sm space-y-4 rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
            <div>
                <h1 class="text-lg font-semibold text-slate-900">Caja de <span x-text="snapshot?.cash_session?.user_name"></span> abierta</h1>
                <p class="text-sm text-slate-500">El mostrador se bloqueó. <span x-text="snapshot?.cash_session?.user_name"></span>, ingresá tu código para seguir vendiendo sin conexión.</p>
            </div>
            <div>
                <label for="offline-code" class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500">Código</label>
                <input id="offline-code" type="password" inputmode="numeric" maxlength="6" x-model="unlockCode" autocomplete="off" class="w-full rounded-[10px] border border-slate-300 px-3 py-2.5 text-center font-mono text-2xl tracking-[0.5em] focus:border-[#0F2A26] focus:outline-none focus:ring-4 focus:ring-[#0F2A26]/10">
                <p x-show="unlockError" x-text="unlockError" class="mt-1 text-sm text-rose-600"></p>
            </div>
            <button type="submit" class="w-full rounded-[10px] bg-[#0F2A26] px-4 py-3 font-bold text-white shadow-sm hover:bg-[#1A3D37]">Desbloquear</button>
        </form>
    </main>

    {{-- Selling --}}
    <main
        x-show="state === 'selling'"
        x-on:keydown.f2.window.prevent="$refs.scan.focus(); $refs.scan.select()"
        x-on:keydown.f9.window.prevent="state === 'selling' && confirm()"
        class="flex flex-1 flex-col lg:flex-row"
    >
        <section class="min-w-0 flex-1 space-y-4 p-4 sm:p-6">
            <div class="flex gap-2">
                <div class="relative flex-1">
                    <input
                        x-ref="scan"
                        x-model="q"
                        x-on:keydown.enter.prevent="scan()"
                        x-on:keydown.escape="q = ''"
                        type="text"
                        autocomplete="off"
                        placeholder="Escaneá o escribí nombre / código…"
                        class="w-full rounded-[10px] border border-slate-200 bg-white py-3 pl-4 pr-28 text-base text-slate-900 shadow-sm placeholder:text-slate-400 focus:border-[#0F2A26] focus:outline-none focus:ring-4 focus:ring-[#0F2A26]/10"
                    >
                    <span class="pointer-events-none absolute right-3 top-1/2 -translate-y-1/2 rounded-md border border-slate-200 bg-slate-50 px-2 py-0.5 text-[10px] font-semibold tracking-wide text-slate-400">F2 SCAN</span>
                </div>
            </div>

            <div
                x-show="alert"
                x-transition.opacity
                class="flex items-center gap-2.5 rounded-[10px] border px-4 py-2.5 text-sm font-medium"
                x-bind:class="alertType === 'success' ? 'border-emerald-200 bg-emerald-50 text-emerald-800' : 'border-rose-200 bg-rose-50 text-rose-700'"
            >
                <span class="flex-1" x-text="alert"></span>
                <button type="button" x-on:click="alert = null" class="opacity-60 hover:opacity-100">&times;</button>
            </div>

            <div class="flex gap-2 overflow-x-auto pb-1">
                <button type="button" x-on:click="categoryId = null" class="inline-flex shrink-0 items-center rounded-full border px-3.5 py-1.5 text-sm font-medium" x-bind:class="categoryId === null ? 'border-[#0F2A26] bg-[#0F2A26] text-white' : 'border-slate-200 bg-white text-slate-600 hover:text-slate-900'">Todos</button>
                <template x-for="category in categories" x-bind:key="category.id">
                    <button type="button" x-on:click="categoryId = category.id" x-text="category.name" class="inline-flex shrink-0 items-center rounded-full border px-3.5 py-1.5 text-sm font-medium" x-bind:class="categoryId === category.id ? 'border-[#0F2A26] bg-[#0F2A26] text-white' : 'border-slate-200 bg-white text-slate-600 hover:text-slate-900'"></button>
                </template>
            </div>

            <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-3">
                <template x-for="product in catalog" x-bind:key="product.id">
                    <button
                        type="button"
                        x-on:click="add(product)"
                        x-bind:disabled="stockOf(product) <= 0"
                        class="relative flex flex-col rounded-[10px] border bg-white p-4 text-left shadow-sm transition"
                        x-bind:class="stockOf(product) <= 0 ? 'cursor-not-allowed border-rose-200 opacity-60' : (cart[product.id] ? 'border-emerald-400 ring-2 ring-emerald-400/30' : 'border-slate-200 hover:border-[#0F2A26]/30 hover:shadow-md')"
                    >
                        <span x-show="cart[product.id]" x-text="cart[product.id]?.quantity" class="absolute -right-2 -top-2 flex size-6 items-center justify-center rounded-full bg-emerald-500 text-xs font-bold text-white shadow"></span>
                        <span class="truncate self-start rounded bg-[#0F2A26] px-1.5 py-0.5 text-[10px] font-semibold text-white" x-text="product.category ?? 'General'"></span>
                        <span class="mt-2.5 font-bold leading-snug text-slate-900" x-text="product.name"></span>
                        <span class="mt-0.5 text-xs text-slate-400" x-text="product.description || (product.barcode ? 'Cód. ' + product.barcode : (product.sku ?? 'Sin código'))"></span>
                        <span class="mt-auto flex items-end justify-between gap-2 pt-4">
                            <span class="text-xl font-bold tabular-nums text-slate-900" x-text="money(product.price)"></span>
                            <span class="shrink-0 rounded-md px-2 py-0.5 text-[11px] font-semibold" x-bind:class="stockOf(product) <= 0 ? 'bg-rose-100 text-rose-700' : 'bg-emerald-100 text-emerald-700'" x-text="stockOf(product) <= 0 ? 'Sin stock' : 'Stock ' + stockOf(product)"></span>
                        </span>
                    </button>
                </template>
                <div x-show="catalog.length === 0" class="col-span-full rounded-[10px] border-2 border-dashed border-slate-200 px-4 py-16 text-center text-slate-400">No hay productos que coincidan con el filtro.</div>
            </div>
        </section>

        <aside class="flex w-full shrink-0 flex-col border-t border-slate-200 bg-white lg:w-[30%] lg:min-w-[22rem] lg:border-l lg:border-t-0">
            <div class="space-y-4 p-5 pb-4 short:space-y-3 short:p-4 short:pb-3">
                <div class="flex items-start justify-between gap-3">
                    <div class="min-w-0">
                        <span class="block text-[11px] font-semibold uppercase tracking-wider text-slate-400">Ticket de venta</span>
                        <span class="block truncate text-2xl font-bold tracking-tight text-slate-900" x-text="'#' + nextNumber"></span>
                        <button type="button" x-show="lastTicket" x-on:click="reprint()" class="mt-0.5 text-xs font-medium text-teal-700 hover:text-teal-900">Reimprimir último</button>
                    </div>
                    <span class="inline-flex shrink-0 items-center gap-1.5 rounded-full bg-amber-100 px-2.5 py-1 text-xs font-semibold text-amber-800">Sin conexión</span>
                </div>

                <div x-on:click.outside="producerOpen = false" class="relative">
                    <button type="button" x-on:click="producerOpen = ! producerOpen" class="flex w-full items-center gap-3 rounded-[10px] border p-3 text-left hover:bg-slate-50" x-bind:class="onAccount && ! producer ? 'border-amber-400 bg-amber-50/50' : 'border-slate-200'">
                        <span class="flex size-10 shrink-0 items-center justify-center rounded-full bg-[#0F2A26]/10 text-sm font-bold text-[#0F2A26]" x-text="producer ? producer.name.charAt(0).toUpperCase() : 'CF'"></span>
                        <span class="min-w-0 flex-1">
                            <span class="block truncate text-sm font-semibold text-slate-900" x-text="producer ? producer.name : 'Consumidor Final'"></span>
                            <span class="block truncate text-[11px] text-slate-400" x-text="producer ? ('Saldo: ' + money(producerBalance(producer)) + (producer.credit_limit !== null ? ' · Límite: ' + money(producer.credit_limit) : '')) : (onAccount ? 'Elegí el productor / estancia para el remito' : 'Sin datos fiscales')"></span>
                        </span>
                    </button>
                    <div x-show="producerOpen" class="absolute inset-x-0 top-full z-30 mt-1 overflow-hidden rounded-[10px] border border-slate-200 bg-white shadow-lg">
                        <div class="border-b border-slate-100 p-2">
                            <input x-model="producerTerm" type="text" autocomplete="off" placeholder="Buscar productor, estancia o CUIT…" class="w-full rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-sm focus:border-[#0F2A26] focus:bg-white focus:outline-none">
                        </div>
                        <ul class="max-h-64 divide-y divide-slate-50 overflow-y-auto text-sm">
                            <li><button type="button" x-on:click="selectProducer(null)" class="w-full px-3 py-2.5 text-left font-medium text-slate-800 hover:bg-slate-50">Consumidor Final</button></li>
                            <template x-for="item in producers" x-bind:key="item.id">
                                <li>
                                    <button type="button" x-on:click="selectProducer(item.id)" class="flex w-full items-center justify-between gap-3 px-3 py-2.5 text-left hover:bg-slate-50">
                                        <span class="min-w-0">
                                            <span class="block truncate font-medium text-slate-800" x-text="item.name"></span>
                                            <span class="block truncate text-[11px] text-slate-400" x-text="[item.business_name, item.cuit].filter(Boolean).join(' · ') || 'Sin datos fiscales'"></span>
                                        </span>
                                        <span x-show="producerBalance(item) > 0" class="shrink-0 text-xs font-semibold tabular-nums text-rose-600" x-text="money(producerBalance(item))"></span>
                                    </button>
                                </li>
                            </template>
                        </ul>
                    </div>
                </div>
            </div>

            <div class="flex items-center justify-between border-y border-slate-100 bg-slate-50/60 px-5 py-2 text-[11px] font-semibold uppercase tracking-wider text-slate-400">
                <span>Productos</span><span>Total</span>
            </div>
            <div class="max-h-96 min-h-32 flex-1 overflow-y-auto px-5 lg:max-h-none short:min-h-24">
                <template x-for="line in lines" x-bind:key="line.product_id">
                    <div class="flex items-center gap-3 border-b border-slate-100 py-3 last:border-b-0">
                        <div class="min-w-0 flex-1">
                            <div class="truncate text-sm font-semibold text-slate-900" x-text="line.name"></div>
                            <div class="text-[11px] tabular-nums text-slate-400" x-text="money(line.price) + ' c/u'"></div>
                        </div>
                        <div class="flex shrink-0 items-center">
                            <button type="button" x-on:click="line.quantity <= 1 ? remove(line.product_id) : setQuantity(line.product_id, line.quantity - 1)" class="flex size-7 items-center justify-center rounded-full border border-slate-200 text-slate-500 hover:text-rose-600">&minus;</button>
                            <span class="w-9 text-center text-sm font-semibold tabular-nums" x-text="line.quantity"></span>
                            <button type="button" x-on:click="setQuantity(line.product_id, line.quantity + 1)" class="flex size-7 items-center justify-center rounded-full border border-slate-200 text-slate-500 hover:text-[#0F2A26]">+</button>
                        </div>
                        <span class="w-20 shrink-0 text-right text-sm font-bold tabular-nums text-slate-900" x-text="money(line.price * line.quantity)"></span>
                    </div>
                </template>
                <div x-show="lines.length === 0" class="py-12 text-center text-sm text-slate-400">Escaneá o tocá un producto para empezar la venta.</div>
            </div>

            <div class="shrink-0 space-y-3 border-t border-slate-200 p-5 short:space-y-2 short:p-4">
                <div x-show="exceedsCreditLimit" class="rounded-[10px] border border-amber-300 bg-amber-50 px-3 py-2 text-xs font-medium text-amber-900">
                    Con el último saldo conocido, este remito supera el límite de crédito. Se registra igual y queda anotado para administración.
                </div>

                <div class="flex items-baseline justify-between">
                    <span class="font-semibold text-slate-700" x-text="onAccount ? 'Total Remito' : 'Total'"></span>
                    <span class="text-3xl font-bold tabular-nums tracking-tight text-slate-900" x-text="money(total)"></span>
                </div>

                <div class="grid grid-cols-2 gap-2">
                    <template x-for="[key, label] in [['cuenta_corriente', 'Cta. Corriente'], ['efectivo', 'Efectivo'], ['mercado_pago', 'Mercado Pago']]" x-bind:key="key">
                        <button
                            type="button"
                            x-on:click="paymentMethod = key"
                            x-text="label"
                            class="flex items-center justify-center rounded-[10px] border px-2 py-3 text-xs font-semibold transition-colors short:py-2"
                            x-bind:class="[key === 'mercado_pago' ? 'col-span-2' : '', paymentMethod === key ? 'border-[#0F2A26] bg-[#0F2A26] text-white shadow-sm' : 'border-slate-200 bg-white text-slate-600 hover:text-slate-900']"
                        ></button>
                    </template>
                </div>
                <p x-show="paymentMethod === 'mercado_pago'" class="text-xs text-slate-500">Confirmá el cobro en el celular antes de registrar la venta.</p>

                <div x-show="paymentMethod === 'efectivo'">
                    <div class="relative">
                        <span class="pointer-events-none absolute left-3.5 top-1/2 -translate-y-1/2 font-semibold" x-bind:class="receivedInvalid ? 'text-rose-500' : 'text-slate-400'">$</span>
                        <input type="text" inputmode="decimal" x-model="received" x-on:keydown.enter.prevent autocomplete="off" placeholder="Monto recibido" class="w-full rounded-[10px] border py-3 pl-8 pr-28 text-lg font-semibold tabular-nums focus:outline-none focus:ring-4 short:py-2" x-bind:class="receivedInvalid ? 'border-rose-400 bg-rose-50 text-rose-700' : 'border-slate-200 bg-white text-slate-900 focus:border-[#0F2A26]'">
                        <span x-show="lines.length && change !== null" class="absolute right-2 top-1/2 -translate-y-1/2 rounded-lg bg-emerald-100 px-2 py-1 text-right text-[11px] font-semibold leading-tight text-emerald-800">Cambio<br><span class="tabular-nums" x-text="money(change)"></span></span>
                    </div>
                </div>

                <div class="flex gap-2">
                    <button type="button" x-on:click="clear()" x-bind:disabled="lines.length === 0" class="flex h-14 shrink-0 items-center justify-center rounded-[10px] border border-slate-200 px-4 text-sm font-semibold text-slate-500 hover:text-rose-600 disabled:cursor-not-allowed disabled:opacity-40 short:h-12">Vaciar</button>
                    <button type="button" x-on:click="confirm()" x-bind:disabled="! canConfirm" class="flex h-14 flex-1 items-center justify-center gap-2 rounded-[10px] bg-[#0F2A26] px-4 font-bold text-white shadow-md hover:bg-[#1A3D37] disabled:cursor-not-allowed disabled:opacity-40 short:h-12">
                        Guardar e Imprimir Ticket
                        <kbd class="rounded bg-white/15 px-1.5 py-0.5 font-sans text-[11px] font-semibold">F9</kbd>
                    </button>
                </div>
            </div>
        </aside>
    </main>
</div>

@livewireScripts
</body>
</html>
