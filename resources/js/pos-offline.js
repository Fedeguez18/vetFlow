/**
 * Selling at the mostrador without internet.
 *
 * While the POS is online this module keeps in the browser a copy of what it needs to sell (catalog with
 * sellable stock and prices, producers, the open caja and the terminal's reserved voucher numbers) and
 * saves the offline page. When the connection drops, /pos/sin-conexion sells from that copy: every sale
 * goes to a queue in this PC with its own id, and is uploaded as soon as the connection is back.
 */

const ROUTES = {
    page: '/pos/sin-conexion',
    snapshot: '/pos/sin-conexion/datos',
    token: '/pos/sin-conexion/token',
    sync: '/pos/sin-conexion/ventas',
    desk: '/pos',
};

const KEYS = {
    snapshot: 'vetflow.pos.snapshot',
    queue: 'vetflow.pos.queue',
    lastUsed: 'vetflow.pos.lastUsed',
    verifier: 'vetflow.pos.verifier',
    provisional: 'vetflow.pos.provisionalSeq',
    synced: 'vetflow.pos.synced',
    pendingVerifier: 'vetflow.pos.pendingVerifier',
};

/** Session cookie (dies with the browser, like the server's) saying this browser unlocked the caja. */
const UNLOCK_COOKIE = 'pos_offline_caja';
const CACHE = 'vetflow-pos-offline-v1';
const PBKDF2_ITERATIONS = 150000;
const SNAPSHOT_EVERY_MS = 3 * 60 * 1000;

/* ------------------------------------------------------------------ storage */

function read(key, fallback = null) {
    try {
        const value = localStorage.getItem(key);
        return value === null ? fallback : JSON.parse(value);
    } catch {
        return fallback;
    }
}

function write(key, value) {
    try {
        localStorage.setItem(key, JSON.stringify(value));
        return true;
    } catch {
        return false;
    }
}

export const store = {
    snapshot: () => read(KEYS.snapshot),
    queue: () => read(KEYS.queue, []),
    lastUsed: () => read(KEYS.lastUsed, {}),

    saveSnapshot(snapshot) {
        write(KEYS.snapshot, { ...snapshot, saved_at: new Date().toISOString() });
    },

    enqueue(sale) {
        const queue = store.queue();
        queue.push(sale);
        if (! write(KEYS.queue, queue)) {
            throw new Error('No se pudo guardar la venta en esta PC (almacenamiento lleno o bloqueado).');
        }
    },

    dequeue(uuid) {
        write(KEYS.queue, store.queue().filter((sale) => sale.uuid !== uuid));
    },

    markError(uuid, message) {
        write(KEYS.queue, store.queue().map((sale) => (sale.uuid === uuid ? { ...sale, error: message } : sale)));
    },

    /** Remember the last voucher number used of a prefix (online or offline), so it is never printed twice. */
    useNumber(voucher) {
        const match = /^([A-Z]+)-(\d+)$/.exec(voucher ?? '');
        if (! match) return;
        const used = store.lastUsed();
        used[match[1]] = Math.max(used[match[1]] ?? 0, Number(match[2]));
        write(KEYS.lastUsed, used);
    },

    logSynced(result) {
        const log = read(KEYS.synced, []);
        log.unshift({ ...result, at: new Date().toISOString() });
        write(KEYS.synced, log.slice(0, 30));
    },
};

/* ------------------------------------------------------------------ numbers and money */

/** Next free number of the terminal's reserved blocks, or null when they ran out. */
function nextVoucher(snapshot, prefix) {
    const last = store.lastUsed()[prefix] ?? 0;
    for (const range of snapshot?.vouchers?.[prefix] ?? []) {
        const candidate = Math.max(range.from, last + 1);
        if (candidate <= range.to) {
            return `${prefix}-${String(candidate).padStart(8, '0')}`;
        }
    }
    return null;
}

function nextProvisional(terminalId) {
    const seq = (read(KEYS.provisional, 0) ?? 0) + 1;
    write(KEYS.provisional, seq);
    return `OFF-${terminalId}-${String(seq).padStart(4, '0')}`;
}

export function money(amount) {
    const value = Math.round(Number(amount || 0) * 100) / 100;
    const decimals = Math.abs(value % 1) < 0.005 ? 0 : 2;
    const formatted = Math.abs(value).toLocaleString('es-AR', { minimumFractionDigits: decimals, maximumFractionDigits: decimals });
    return `${value < 0 ? '-' : ''}$${formatted}`;
}

/** Amount of a line like the server's Quantity::lineTotal: weight lines are grams at a price per kg. */
export function lineTotal(price, quantity, byWeight) {
    return Math.round((price * quantity) / (byWeight ? 1000 : 1) * 100) / 100;
}

/** "3" units, or "350 g" / "2,5 kg" like the server's Quantity::format. */
export function formatQuantity(quantity, byWeight) {
    if (! byWeight) return String(quantity);
    if (Math.abs(quantity) < 1000) return `${quantity} g`;
    return `${(quantity / 1000).toLocaleString('es-AR', { maximumFractionDigits: 3 })} kg`;
}

/** "1500.50", "1.500,50" and "25.000" like the server's Pricing::parse. */
export function parseAmount(value) {
    let amount = String(value ?? '').replace(/[\s$%]/g, '');
    if (amount === '') return null;
    if (amount.includes(',') || /^\d{1,3}(\.\d{3})+$/.test(amount)) {
        amount = amount.replace(/\./g, '').replace(',', '.');
    }
    return /^-?\d+(\.\d+)?$/.test(amount) ? Number(amount) : null;
}

function uuid() {
    if (crypto.randomUUID) return crypto.randomUUID();
    const bytes = crypto.getRandomValues(new Uint8Array(16));
    bytes[6] = (bytes[6] & 0x0f) | 0x40;
    bytes[8] = (bytes[8] & 0x3f) | 0x80;
    const hex = [...bytes].map((b) => b.toString(16).padStart(2, '0')).join('');
    return `${hex.slice(0, 8)}-${hex.slice(8, 12)}-${hex.slice(12, 16)}-${hex.slice(16, 20)}-${hex.slice(20)}`;
}

/**
 * Units of each product and remito totals of each producer still waiting in the queue. The server hasn't
 * seen them, so they are never in the snapshot: the stock and balances shown subtract / add them.
 */
function pendingTotals() {
    const units = {};
    const debt = {};
    for (const sale of store.queue()) {
        for (const item of sale.items) units[item.product_id] = (units[item.product_id] ?? 0) + item.quantity;
        if (sale.type === 'remito') debt[sale.producer_id] = (debt[sale.producer_id] ?? 0) + sale.total;
    }
    return { units, debt };
}

/* ------------------------------------------------------------------ unlock without internet */

function cookie(name) {
    return document.cookie.split('; ').find((row) => row.startsWith(`${name}=`))?.split('=')[1] ?? null;
}

function setUnlockCookie(cashSessionId) {
    document.cookie = `${UNLOCK_COOKIE}=${cashSessionId}; path=/; SameSite=Strict`;
}

function toBase64(buffer) {
    return btoa(String.fromCharCode(...new Uint8Array(buffer)));
}

function fromBase64(text) {
    return Uint8Array.from(atob(text), (c) => c.charCodeAt(0));
}

async function derive(code, salt, iterations) {
    const key = await crypto.subtle.importKey('raw', new TextEncoder().encode(code), 'PBKDF2', false, ['deriveBits']);
    return toBase64(await crypto.subtle.deriveBits({ name: 'PBKDF2', hash: 'SHA-256', salt, iterations }, key, 256));
}

/**
 * Lock screen: as the user types their 6-digit code, keep a slow hash of it for this tab. Once the server
 * accepts the code and the mostrador opens, it becomes the way to unlock the caja without internet.
 */
async function rememberCodeTyped(code) {
    if (! /^\d{6}$/.test(code) || ! crypto.subtle) return;
    const salt = crypto.getRandomValues(new Uint8Array(16));
    const hash = await derive(code, salt, PBKDF2_ITERATIONS);
    sessionStorage.setItem(KEYS.pendingVerifier, JSON.stringify({ salt: toBase64(salt), hash, iterations: PBKDF2_ITERATIONS, at: Date.now() }));
}

function promotePendingVerifier(snapshot) {
    let pending = null;
    try {
        pending = JSON.parse(sessionStorage.getItem(KEYS.pendingVerifier) ?? 'null');
    } catch {
        pending = null;
    }
    if (! pending || ! snapshot?.cash_session || ! snapshot.unlocked || Date.now() - pending.at > 15 * 60 * 1000) return;
    write(KEYS.verifier, { cash_session_id: snapshot.cash_session.id, ...pending });
    sessionStorage.removeItem(KEYS.pendingVerifier);
}

async function verifyCode(snapshot, code) {
    const verifier = read(KEYS.verifier);
    if (! verifier || verifier.cash_session_id !== snapshot?.cash_session?.id || ! crypto.subtle) return false;
    return (await derive(code, fromBase64(verifier.salt), verifier.iterations)) === verifier.hash;
}

/* ------------------------------------------------------------------ server */

async function fetchJson(url, options = {}) {
    const response = await fetch(url, {
        credentials: 'same-origin',
        cache: 'no-store',
        ...options,
        headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest', ...(options.headers ?? {}) },
    });
    const type = response.headers.get('content-type') ?? '';
    const body = type.includes('application/json') ? await response.json() : null;
    return { ok: response.ok, status: response.status, body };
}

export async function refreshSnapshot() {
    const { ok, body } = await fetchJson(ROUTES.snapshot);
    if (! ok || ! body?.terminal) return null;
    store.saveSnapshot(body);
    if (body.unlocked && body.cash_session) setUnlockCookie(body.cash_session.id);
    promotePendingVerifier(body);
    return body;
}

let syncing = null;

/**
 * Upload the queued sales, oldest first. A sale leaves the queue only when the server answers that it
 * registered it (or had it already); a refused one stays, marked with the reason, so nothing is lost.
 */
export function syncQueue(onProgress = () => {}) {
    if (syncing) return syncing;

    const run = async () => {
        const result = { uploaded: 0, failed: 0, offline: false, review: 0 };
        if (store.queue().length === 0) return result;

        let token;
        try {
            const response = await fetchJson(ROUTES.token);
            token = response.body?.token;
            if (! response.ok || ! token) throw new Error('token');
        } catch {
            return { ...result, offline: true };
        }

        for (const sale of store.queue()) {
            onProgress(result);
            let response;
            try {
                const { error, ...payload } = sale;
                response = await fetchJson(ROUTES.sync, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': token },
                    body: JSON.stringify(payload),
                });
            } catch {
                return { ...result, offline: true };
            }

            if (response.ok) {
                store.dequeue(sale.uuid);
                store.logSynced({ uuid: sale.uuid, printed: sale.voucher_number ?? sale.provisional_number, ...response.body });
                result.uploaded++;
                if (response.body?.needs_review) result.review++;
            } else if (response.status === 422) {
                const errors = Object.values(response.body?.errors ?? {}).flat().join(' ');
                store.markError(sale.uuid, errors || 'El servidor rechazó la venta.');
                result.failed++;
            } else {
                // Session expired, server down, rate limited... try again later with the queue intact.
                return { ...result, offline: true };
            }
        }

        onProgress(result);
        return result;
    };

    const locked = navigator.locks?.request ? navigator.locks.request('vetflow-pos-sync', run) : run();
    syncing = locked.finally(() => { syncing = null; });
    return syncing;
}

/** Save the offline page and every file it loads, so it opens even with the PC disconnected. */
async function saveOfflinePage() {
    if (! ('caches' in window)) return;
    try {
        const response = await fetch(ROUTES.page, { credentials: 'same-origin', cache: 'no-store' });
        if (! response.ok || ! (response.headers.get('content-type') ?? '').includes('text/html')) return;
        const html = await response.clone().text();
        const sameOrigin = (url) => url.origin === location.origin;
        const assets = [...html.matchAll(/(?:src|href)="([^"]+)"/g)]
            .map((match) => new URL(match[1].replace(/&amp;/g, '&'), location.href))
            .filter((url) => sameOrigin(url) && /\.(css|js|woff2?|ico|svg|png)$|livewire/.test(url.pathname))
            .map((url) => url.href);

        // Fonts and images the stylesheets point to.
        for (const css of assets.filter((url) => url.endsWith('.css'))) {
            const text = await (await fetch(css, { credentials: 'same-origin' })).text();
            for (const match of text.matchAll(/url\(\s*['"]?([^'")]+)['"]?\s*\)/g)) {
                const url = new URL(match[1], css);
                if (sameOrigin(url) && ! url.href.startsWith('data:') && ! assets.includes(url.href)) assets.push(url.href);
            }
        }
        const cache = await caches.open(CACHE);
        await cache.put(ROUTES.page, response);
        await Promise.all(assets.map((url) => cache.add(url).catch(() => {})));

        const keep = new Set([new URL(ROUTES.page, location.href).href, ...assets]);
        for (const request of await cache.keys()) {
            if (! keep.has(request.url)) await cache.delete(request);
        }
    } catch {
        // Saving the page is best effort: the next visit tries again.
    }
}

function registerServiceWorker() {
    if ('serviceWorker' in navigator) {
        navigator.serviceWorker.register('/pos-sw.js', { scope: '/pos' }).catch(() => {});
    }
}

/* ------------------------------------------------------------------ online pages */

function notice(id, html, tone = 'amber') {
    let element = document.getElementById(id);
    if (! html) {
        element?.remove();
        return null;
    }
    if (! element) {
        element = document.createElement('div');
        element.id = id;
        document.body.appendChild(element);
    }
    const tones = {
        amber: 'border-amber-300 bg-amber-50 text-amber-900',
        rose: 'border-rose-300 bg-rose-50 text-rose-800',
        emerald: 'border-emerald-300 bg-emerald-50 text-emerald-900',
    };
    element.className = `fixed inset-x-4 bottom-4 z-[70] mx-auto flex max-w-2xl flex-wrap items-center gap-3 rounded-xl border px-4 py-3 text-sm font-medium shadow-lg ${tones[tone]}`;
    element.innerHTML = html;
    return element;
}

function blockingOverlay(html) {
    let overlay = document.getElementById('pos-offline-overlay');
    if (! html) {
        overlay?.remove();
        return null;
    }
    if (! overlay) {
        overlay = document.createElement('div');
        overlay.id = 'pos-offline-overlay';
        overlay.className = 'fixed inset-0 z-[80] flex items-center justify-center bg-slate-900/60 p-4';
        document.body.appendChild(overlay);
    }
    overlay.innerHTML = `<div class="w-full max-w-md space-y-3 rounded-2xl bg-white p-6 text-sm text-slate-700 shadow-xl">${html}</div>`;
    return overlay;
}

/**
 * Sales made without internet go up before selling online again: the online POS takes its numbers from
 * the same blocks, and the stock it shows has to include them.
 */
async function uploadPendingBeforeSelling() {
    const pending = store.queue().length;
    if (pending === 0) return;

    blockingOverlay(`<h2 class="text-lg font-semibold text-slate-900">Subiendo ventas hechas sin conexión</h2>
        <p>Hay ${pending} ${pending === 1 ? 'venta guardada' : 'ventas guardadas'} en esta PC. Se suben antes de seguir vendiendo.</p>`);

    const result = await syncQueue();
    const left = store.queue().length;

    if (left === 0) {
        blockingOverlay(null);
        if (result.uploaded > 0) location.reload();
        return;
    }

    const overlay = blockingOverlay(`<h2 class="text-lg font-semibold text-slate-900">Quedan ${left} ${left === 1 ? 'venta' : 'ventas'} sin subir</h2>
        <p>${result.offline ? 'No hay conexión con el servidor.' : 'El servidor rechazó alguna venta: avisá a administración.'} Las ventas siguen guardadas en esta PC.</p>
        <div class="flex gap-2 pt-1">
            <button type="button" data-action="retry" class="flex-1 rounded-[10px] bg-[#0F2A26] px-4 py-2.5 font-bold text-white">Reintentar</button>
            <button type="button" data-action="offline" class="flex-1 rounded-[10px] px-4 py-2.5 font-semibold text-slate-600 ring-1 ring-slate-300">Vender sin conexión</button>
        </div>`);
    overlay.querySelector('[data-action=retry]').addEventListener('click', () => uploadPendingBeforeSelling());
    overlay.querySelector('[data-action=offline]').addEventListener('click', () => { location.href = ROUTES.page; });
}

let lostNoticeShown = false;

function connectionLost() {
    if (lostNoticeShown || ! store.snapshot()) return;
    lostNoticeShown = true;
    const element = notice('pos-offline-notice', `<span class="flex-1">Se perdió la conexión a internet.</span>
        <a href="${ROUTES.page}" class="rounded-lg bg-amber-600 px-3 py-1.5 text-xs font-bold text-white hover:bg-amber-500">Vender sin conexión</a>`);
    element?.querySelector('a')?.focus();
    waitForConnection();
}

let waiting = false;

async function waitForConnection() {
    if (waiting) return;
    waiting = true;
    while (true) {
        await new Promise((resolve) => setTimeout(resolve, 5000));
        try {
            const response = await fetch('/up', { cache: 'no-store' });
            if (response.ok) break;
        } catch {
            // still offline
        }
    }
    waiting = false;
    lostNoticeShown = false;
    notice('pos-offline-notice', null);
}

/** A failed request of the page (Livewire included) because the network is gone, not because the server answered an error. */
function watchNetworkFailures() {
    const originalFetch = window.fetch.bind(window);
    window.fetch = (...args) => originalFetch(...args).catch((error) => {
        if (error?.name !== 'AbortError') connectionLost();
        throw error;
    });
    window.addEventListener('offline', connectionLost);
}

async function startOnlineDesk() {
    registerServiceWorker();
    watchNetworkFailures();
    await uploadPendingBeforeSelling();
    await refreshSnapshot();
    saveOfflinePage();

    let timer = null;
    window.addEventListener('pos-sale-registered', (event) => {
        store.useNumber(event.detail?.voucher);
        clearTimeout(timer);
        timer = setTimeout(() => refreshSnapshot().catch(() => {}), 1500);
    });
    setInterval(() => refreshSnapshot().catch(() => {}), SNAPSHOT_EVERY_MS);
}

async function startLockScreen() {
    registerServiceWorker();
    watchNetworkFailures();
    document.addEventListener('input', (event) => {
        if (event.target?.id === 'code') rememberCodeTyped(event.target.value).catch(() => {});
    });
    await uploadPendingBeforeSelling();
    await refreshSnapshot().catch(() => {});
    saveOfflinePage();
}

/* ------------------------------------------------------------------ offline page */

function ticketHtml(sale, snapshot) {
    const escape = (text) => String(text ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[c]);
    const producer = snapshot.producers.find((p) => p.id === sale.producer_id);
    const lines = sale.items.map((item) => `<div>${escape(item.name)}</div>
        <div class="row muted"><span>${formatQuantity(item.quantity, item.by_weight)} x ${money(item.unit_price)}${item.by_weight ? '/kg' : ''}</span><span>${money(lineTotal(item.unit_price, item.quantity, item.by_weight))}</span></div>`).join('');
    return `<h1>veteFlow</h1><div class="c muted">Veterinaria rural</div><hr>
        <div class="row"><span>${sale.type === 'remito' ? 'REMITO' : 'TICKET'}</span><span>${escape(sale.voucher_number ?? sale.provisional_number)}</span></div>
        ${sale.voucher_number ? '' : '<div class="c muted">Comprobante provisorio: el número definitivo se asigna al volver internet</div>'}
        <div class="row"><span>Fecha</span><span>${new Date(sale.sold_at).toLocaleString('es-AR', { day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit' })}</span></div>
        ${producer ? `<div class="row"><span>Productor</span><span>${escape(producer.name)}</span></div>` : ''}
        ${producer?.cuit ? `<div class="row"><span>CUIT</span><span>${escape(producer.cuit)}</span></div>` : ''}
        <div class="row"><span>Cobro</span><span>${escape(snapshot.methods[sale.payment_method] ?? sale.payment_method)}</span></div>
        <hr>${lines}<hr>
        <div class="row total"><span>TOTAL</span><span>${money(sale.total)}</span></div>
        ${sale.type === 'remito' ? '<br><br><div class="c">______________________</div><div class="c muted">Firma y aclaración</div>' : ''}
        <hr><div class="c muted">Comprobante no válido como factura</div>`;
}

function printTicket(html) {
    const frame = document.createElement('iframe');
    frame.style.cssText = 'position:fixed;right:0;bottom:0;width:0;height:0;border:0;';
    document.body.appendChild(frame);
    const doc = frame.contentWindow.document;
    doc.open();
    doc.write(`<!DOCTYPE html><html><head><meta charset="utf-8"><title>Ticket</title><style>
        body{font-family:ui-monospace,monospace;font-size:12px;width:72mm;margin:0 auto;padding:4mm 0;color:#000}
        h1{font-size:16px;margin:0;text-align:center}.c{text-align:center}.r{text-align:right}
        .row{display:flex;justify-content:space-between;gap:8px}.muted{color:#444;font-size:11px}
        hr{border:0;border-top:1px dashed #000;margin:6px 0}.total{font-size:15px;font-weight:bold}
    </style></head><body>${html}</body></html>`);
    doc.close();
    frame.contentWindow.focus();
    frame.contentWindow.print();
    setTimeout(() => frame.remove(), 1000);
}

function posOffline() {
    return {
        snapshot: null,
        state: 'loading',
        unlockCode: '',
        unlockError: '',
        q: '',
        categoryId: null,
        cart: {},
        producerId: null,
        producerTerm: '',
        producerOpen: false,
        paymentMethod: 'efectivo',
        received: '',
        alert: null,
        alertType: 'error',
        queueCount: 0,
        queueErrors: 0,
        online: false,
        syncState: 'idle',
        syncMessage: '',
        lastTicket: null,
        pending: { units: {}, debt: {} },

        init() {
            this.snapshot = store.snapshot();
            this.refreshQueue();

            if (! this.snapshot) {
                this.state = 'empty';
            } else if (! this.snapshot.cash_session) {
                this.state = 'nocaja';
            } else if (cookie(UNLOCK_COOKIE) === String(this.snapshot.cash_session.id)) {
                this.state = 'selling';
            } else {
                this.state = read(KEYS.verifier)?.cash_session_id === this.snapshot.cash_session.id ? 'locked' : 'cantunlock';
            }

            this.$nextTick(() => this.focusScan());
            window.addEventListener('online', () => this.checkConnection());
            this.checkConnection();
            setInterval(() => this.checkConnection(), 15000);
        },

        refreshQueue() {
            const queue = store.queue();
            this.queueCount = queue.length;
            this.queueErrors = queue.filter((sale) => sale.error).length;
            this.pending = pendingTotals();
        },

        async checkConnection() {
            if (this.syncState === 'syncing') return;
            try {
                const response = await fetch('/up', { cache: 'no-store' });
                this.online = response.ok;
            } catch {
                this.online = false;
            }
            if (! this.online) {
                if (this.syncState !== 'error') this.syncState = 'idle';
                return;
            }
            this.syncState = 'syncing';
            this.syncMessage = this.queueCount ? `Volvió internet: subiendo ${this.queueCount} ${this.queueCount === 1 ? 'venta' : 'ventas'}…` : 'Volvió internet.';
            const result = await syncQueue();
            this.refreshQueue();
            if (this.queueCount === 0 && ! result.offline) {
                // Stock and balances as the server has them now, sales just uploaded included.
                this.snapshot = (await refreshSnapshot().catch(() => null)) ?? this.snapshot;
                this.refreshQueue();
                this.syncState = 'done';
                this.syncMessage = result.uploaded
                    ? `Volvió internet: se subieron ${result.uploaded} ${result.uploaded === 1 ? 'venta' : 'ventas'} a la caja y la auditoría${result.review ? ` (${result.review} para revisar en administración)` : ''}.`
                    : 'Volvió internet.';
            } else {
                this.syncState = 'error';
                this.syncMessage = result.offline
                    ? 'Hay conexión pero el servidor todavía no responde. Se reintenta solo.'
                    : `${this.queueErrors} ${this.queueErrors === 1 ? 'venta fue rechazada' : 'ventas fueron rechazadas'} por el servidor: quedan guardadas en esta PC, avisá a administración.`;
            }
        },

        backToDesk() {
            location.href = ROUTES.desk;
        },

        async unlock() {
            this.unlockError = '';
            if (await verifyCode(this.snapshot, this.unlockCode.trim())) {
                setUnlockCookie(this.snapshot.cash_session.id);
                this.unlockCode = '';
                this.state = 'selling';
                this.$nextTick(() => this.focusScan());
            } else {
                this.unlockError = 'Código incorrecto.';
            }
        },

        focusScan() {
            this.$refs.scan?.focus();
        },

        notify(message, type = 'error') {
            this.alert = message;
            this.alertType = type;
            clearTimeout(this.alertTimer);
            this.alertTimer = setTimeout(() => { this.alert = null; }, type === 'error' ? 6000 : 3500);
        },

        stockOf(product) {
            return Math.max(0, product.stock - (this.pending.units[product.id] ?? 0));
        },

        get categories() {
            return this.snapshot?.categories ?? [];
        },

        get catalog() {
            const term = this.q.trim().toLowerCase();
            return (this.snapshot?.products ?? [])
                .filter((product) => ! this.categoryId || product.category_id === this.categoryId)
                .filter((product) => ! term || [product.name, product.description, product.sku, product.barcode].join(' ').toLowerCase().includes(term))
                .slice(0, 60);
        },

        get producers() {
            const term = this.producerTerm.trim().toLowerCase();
            return (this.snapshot?.producers ?? []).filter((producer) => ! term || [producer.name, producer.business_name, producer.cuit].join(' ').toLowerCase().includes(term));
        },

        get producer() {
            return (this.snapshot?.producers ?? []).find((producer) => producer.id === this.producerId) ?? null;
        },

        producerBalance(producer) {
            return producer.balance + (this.pending.debt[producer.id] ?? 0);
        },

        get lines() {
            return Object.values(this.cart);
        },

        get total() {
            return Math.round(this.lines.reduce((sum, line) => sum + lineTotal(line.price, line.quantity, line.by_weight), 0) * 100) / 100;
        },

        get onAccount() {
            return this.paymentMethod === 'cuenta_corriente';
        },

        get receivedAmount() {
            return parseAmount(this.received);
        },

        get receivedInvalid() {
            return this.paymentMethod === 'efectivo' && this.received.trim() !== '' && (this.receivedAmount === null || this.receivedAmount < this.total);
        },

        get change() {
            const received = this.receivedAmount;
            return received !== null && received >= this.total ? Math.round((received - this.total) * 100) / 100 : null;
        },

        get exceedsCreditLimit() {
            const producer = this.producer;
            return this.onAccount && producer && producer.credit_limit !== null && this.producerBalance(producer) + this.total > producer.credit_limit;
        },

        get nextNumber() {
            return nextVoucher(this.snapshot, this.onAccount ? 'R' : 'C') ?? 'Provisorio';
        },

        get canConfirm() {
            return this.lines.length > 0 && ! this.receivedInvalid && ! (this.onAccount && ! this.producer);
        },

        add(product) {
            const stock = this.stockOf(product);
            const inCart = this.cart[product.id]?.quantity ?? 0;
            const byWeight = Boolean(product.sold_by_weight);
            if (byWeight && inCart > 0) {
                this.focusWeight(product.id);
                return;
            }
            if (inCart + 1 > stock) {
                this.notify(stock > 0 ? `Stock insuficiente de ${product.name}: disponible ${formatQuantity(stock, byWeight)}.` : `${product.name} no tiene stock.`);
                return;
            }
            // Weight enters once with 1 kg (or what is left) and the grams field gets the focus.
            this.cart[product.id] = { product_id: product.id, name: product.name, price: product.price, stock, quantity: byWeight ? Math.min(1000, stock) : inCart + 1, by_weight: byWeight };
            this.notify(`${product.name} añadido al ticket.`, 'success');
            if (byWeight) this.focusWeight(product.id);
        },

        focusWeight(productId) {
            this.$nextTick(() => {
                const input = document.getElementById(`offline-weight-${productId}`);
                input?.focus();
                input?.select();
            });
        },

        scan() {
            const code = this.q.trim();
            if (! code) return;
            const products = this.snapshot.products;
            const exact = products.find((product) => product.barcode === code || (product.sku ?? '').toUpperCase() === code.toUpperCase());
            const matches = exact ? [exact] : this.catalog;
            if (matches.length === 1) {
                this.add(matches[0]);
                this.q = '';
            } else {
                this.notify(matches.length ? 'Hay varias coincidencias: elegí el producto en la grilla.' : `No se encontró ningún producto para «${code}».`);
            }
        },

        setQuantity(productId, quantity) {
            const line = this.cart[productId];
            if (! line) return;
            let value = Math.max(1, parseInt(quantity, 10) || 1);
            if (value > line.stock) {
                value = Math.max(1, line.stock);
                this.notify(`Stock insuficiente de ${line.name}: disponible ${formatQuantity(line.stock, line.by_weight)}.`);
            }
            line.quantity = value;
        },

        remove(productId) {
            delete this.cart[productId];
        },

        clear() {
            this.cart = {};
            this.producerId = null;
            this.paymentMethod = 'efectivo';
            this.received = '';
            this.focusScan();
        },

        selectProducer(id) {
            this.producerId = id;
            this.producerOpen = false;
            this.producerTerm = '';
            this.focusScan();
        },

        confirm() {
            if (! this.canConfirm) {
                if (this.onAccount && ! this.producer) this.notify('Para vender a remito tenés que elegir el productor / estancia.');
                return;
            }

            const type = this.onAccount ? 'remito' : 'contado';
            const voucher = nextVoucher(this.snapshot, type === 'remito' ? 'R' : 'C');
            const sale = {
                uuid: uuid(),
                sold_at: new Date().toISOString(),
                cash_session_id: this.snapshot.cash_session.id,
                type,
                payment_method: this.paymentMethod,
                producer_id: this.producerId,
                voucher_number: voucher,
                provisional_number: voucher ? null : nextProvisional(this.snapshot.terminal.id),
                items: this.lines.map((line) => ({ product_id: line.product_id, quantity: line.quantity, unit_price: line.price, name: line.name, by_weight: Boolean(line.by_weight) })),
                total: this.total,
            };

            try {
                store.enqueue(sale);
            } catch (error) {
                this.notify(error.message);
                return;
            }
            if (voucher) store.useNumber(voucher);

            const change = this.paymentMethod === 'efectivo' ? this.change : null;
            this.lastTicket = ticketHtml(sale, this.snapshot);
            this.refreshQueue();
            this.clear();
            this.notify(`Venta ${voucher ?? sale.provisional_number} guardada en esta PC.${change ? ` Cambio: ${money(change)}.` : ''}`, 'success');
            this.$nextTick(() => requestAnimationFrame(() => printTicket(this.lastTicket)));
            if (navigator.onLine) this.checkConnection();
        },

        reprint() {
            if (this.lastTicket) printTicket(this.lastTicket);
        },

        money,
        lineTotal,
        formatQuantity,
    };
}

/* ------------------------------------------------------------------ boot */

document.addEventListener('alpine:init', () => {
    window.Alpine.data('posOffline', posOffline);
});

const page = document.body?.dataset.posPage;

if (page === 'desk') {
    startOnlineDesk().catch(() => {});
} else if (page === 'lock') {
    startLockScreen().catch(() => {});
} else if (page === 'offline') {
    registerServiceWorker();
}
