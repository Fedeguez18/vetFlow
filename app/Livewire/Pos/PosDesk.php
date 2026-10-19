<?php

namespace App\Livewire\Pos;

use App\Models\Category;
use App\Models\Producer;
use App\Models\Product;
use App\Models\Sale;
use App\Services\CheckoutService;
use App\Support\Pricing;
use Exception;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts::pos')]
#[Title('Mostrador POS')]
class PosDesk extends Component
{
    public const PAYMENT_METHODS = [
        'efectivo' => 'Efectivo',
        'qr' => 'QR/Transferencia',
        'tarjeta' => 'Tarjetas',
        'cuenta_corriente' => 'Cuenta Corriente (A Remito)',
    ];

    /**
     * Products shown in the catalog grid at once.
     */
    public const CATALOG_LIMIT = 60;

    public string $search = '';

    public ?int $categoryId = null;

    /**
     * Cart lines keyed by product id.
     *
     * @var array<int, array{product_id: int, name: string, barcode: ?string, price: string, stock: int, quantity: int}>
     */
    public array $cart = [];

    public ?int $producerId = null;

    public string $paymentMethod = 'efectivo';

    public string $received = '';

    public ?int $lastSaleId = null;

    public ?string $lastPaymentMethod = null;

    public ?string $alert = null;

    public string $alertType = 'error';

    /**
     * Bumped on every notification so the view can restart the auto-hide timer.
     */
    public int $alertId = 0;

    /**
     * Handle the Enter key from the search input or the USB barcode scanner.
     */
    public function addByCode(string $code): void
    {
        $code = trim($code);

        if ($code === '') {
            return;
        }

        $product = Product::query()
            ->with('category')
            ->where('is_active', true)
            ->where(fn ($query) => $query->where('barcode', $code)->orWhere('sku', strtoupper($code)))
            ->first();

        if (! $product) {
            $matches = $this->searchQuery($code)->with('category')->limit(2)->get();

            if ($matches->count() !== 1) {
                $this->search = $code;
                $this->categoryId = null;
                $this->dispatch('scan-restore', code: $code);
                $this->notify($matches->isEmpty()
                    ? "No se encontró ningún producto para «{$code}»."
                    : 'Hay varias coincidencias: elegí el producto en la grilla.');

                return;
            }

            $product = $matches->first();
        }

        if ($this->addToCart($product)) {
            $this->search = '';
            $this->notify("Escaneado: {$code} — {$product->name} añadido al ticket.", 'success');
        }
    }

    /**
     * Add a product picked from the catalog grid.
     */
    public function addProduct(int $productId): void
    {
        $product = Product::query()->with('category')->where('is_active', true)->find($productId);

        if (! $product) {
            $this->notify('El producto no existe o está inactivo.');

            return;
        }

        if ($this->addToCart($product)) {
            $this->notify("{$product->name} añadido al ticket.", 'success');
        }
    }

    public function setQuantity(int $productId, mixed $quantity): void
    {
        if (! isset($this->cart[$productId])) {
            return;
        }

        $quantity = max(1, (int) $quantity);
        $stock = $this->cart[$productId]['stock'];

        if ($quantity > $stock) {
            $quantity = max(1, $stock);
            $this->notify("Stock insuficiente de {$this->cart[$productId]['name']}: disponible {$stock}.");
        }

        $this->cart[$productId]['quantity'] = $quantity;
    }

    public function removeItem(int $productId): void
    {
        unset($this->cart[$productId]);
    }

    public function selectProducer(?int $producerId): void
    {
        $this->producerId = $producerId;
        $this->dispatch('focus-scan');
    }

    public function clearCart(): void
    {
        $this->reset('cart', 'producerId', 'paymentMethod', 'received', 'search', 'alert');
        $this->dispatch('focus-scan');
    }

    /**
     * Confirm the sale through the checkout service and trigger the ticket print.
     */
    public function confirm(CheckoutService $checkout): void
    {
        if ($this->cart === []) {
            $this->notify('El ticket está vacío.');

            return;
        }

        if (! array_key_exists($this->paymentMethod, self::PAYMENT_METHODS)) {
            $this->notify('Elegí un medio de cobro.');

            return;
        }

        $onAccount = $this->paymentMethod === 'cuenta_corriente';

        if ($onAccount && ! $this->producerId) {
            $this->notify('Para vender a remito tenés que elegir el productor / estancia.');

            return;
        }

        $change = null;

        if ($this->paymentMethod === 'efectivo' && trim($this->received) !== '') {
            if ($this->receivedAmount === null || $this->receivedAmount < $this->total) {
                $this->notify('El monto recibido es menor al total.');

                return;
            }

            $change = $this->change;
        }

        $cartItems = array_values(array_map(fn (array $line) => [
            'product_id' => $line['product_id'],
            'quantity' => $line['quantity'],
        ], $this->cart));

        try {
            $sale = $checkout->execute($cartItems, [
                'type' => $onAccount ? 'remito' : 'contado',
                'producer_id' => $this->producerId,
                'payment_method' => $this->paymentMethod,
                'user_id' => auth()->id(),
            ]);
        } catch (Exception $e) {
            $this->notify($e->getMessage());

            return;
        }

        $this->lastSaleId = $sale->id;
        $this->lastPaymentMethod = $this->paymentMethod;
        $this->reset('cart', 'producerId', 'paymentMethod', 'received', 'search');
        unset($this->lastSale, $this->producers, $this->catalog, $this->nextVoucher);

        $this->notify("Venta {$sale->voucher_number} registrada.".($change ? ' Cambio: '.Pricing::money($change).'.' : ''), 'success');
        $this->dispatch('print-ticket');
    }

    /**
     * Categories with active products, for the filter chips.
     *
     * @return Collection<int, Category>
     */
    #[Computed]
    public function categories(): Collection
    {
        return Category::query()
            ->whereHas('products', fn ($query) => $query->where('is_active', true))
            ->orderBy('id')
            ->get(['id', 'name']);
    }

    #[Computed]
    public function catalog(): Collection
    {
        return $this->catalogQuery()->with(['category', 'nextLot'])->withSellableStock()->orderBy('name')->limit(self::CATALOG_LIMIT)->get();
    }

    #[Computed]
    public function catalogCount(): int
    {
        return $this->catalogQuery()->count();
    }

    #[Computed]
    public function producers(): Collection
    {
        return Producer::query()
            ->active()
            ->where('name', '!=', Producer::WALK_IN)
            ->orderBy('name')
            ->get(['id', 'name', 'business_name', 'cuit', 'balance']);
    }

    #[Computed]
    public function selectedProducer(): ?Producer
    {
        return $this->producerId ? $this->producers->firstWhere('id', $this->producerId) : null;
    }

    /**
     * Voucher number the next sale will get (preview, assigned for real on checkout).
     */
    #[Computed]
    public function nextVoucher(): string
    {
        return app(CheckoutService::class)->nextVoucherNumber($this->paymentMethod === 'cuenta_corriente' ? 'remito' : 'contado');
    }

    #[Computed]
    public function total(): float
    {
        return round(array_sum(array_map(
            fn (array $line) => (float) $line['price'] * $line['quantity'],
            $this->cart,
        )), 2);
    }

    /**
     * Cash handed over by the customer, accepting "1500.50", "1.500,50" and "25.000".
     */
    #[Computed]
    public function receivedAmount(): ?float
    {
        $amount = Pricing::parse($this->received);

        return $amount === null ? null : round($amount, 2);
    }

    #[Computed]
    public function change(): ?float
    {
        $received = $this->receivedAmount;

        return $received !== null && $received >= $this->total ? round($received - $this->total, 2) : null;
    }

    #[Computed]
    public function lastSale(): ?Sale
    {
        return $this->lastSaleId
            ? Sale::with(['saleItems.product', 'producer'])->find($this->lastSaleId)
            : null;
    }

    public function render()
    {
        return view('livewire.pos.pos-desk', [
            'methods' => self::PAYMENT_METHODS,
        ]);
    }

    protected function addToCart(Product $product): bool
    {
        $current = $this->cart[$product->id]['quantity'] ?? 0;
        $available = $product->sellableStock();

        if ($current + 1 > $available) {
            $expired = $product->expiredStock();

            $this->notify($available === 0 && $expired > 0
                ? "No se puede vender {$product->name}: las {$expired} unidades en stock están vencidas."
                : "Sin stock suficiente de {$product->name} (disponible {$available}).");

            return false;
        }

        $this->cart[$product->id] = [
            'product_id' => $product->id,
            'name' => $product->name,
            'barcode' => $product->barcode,
            'price' => (string) $product->price,
            'stock' => $available,
            'quantity' => $current + 1,
        ];

        $this->dispatch('focus-scan');

        return true;
    }

    protected function catalogQuery()
    {
        return $this->searchQuery(trim($this->search))
            ->when($this->categoryId, fn ($query) => $query->where('category_id', $this->categoryId));
    }

    protected function searchQuery(string $term)
    {
        return Product::query()
            ->where('is_active', true)
            ->when($term !== '', fn ($query) => $query->where(fn ($query) => $query
                ->where('name', 'like', "%{$term}%")
                ->orWhere('barcode', 'like', "{$term}%")
                ->orWhere('sku', 'like', "{$term}%")));
    }

    protected function notify(string $message, string $type = 'error'): void
    {
        $this->alert = $message;
        $this->alertType = $type;
        $this->alertId++;
    }
}
