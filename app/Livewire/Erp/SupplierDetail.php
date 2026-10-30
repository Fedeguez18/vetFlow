<?php

namespace App\Livewire\Erp;

use App\Livewire\Concerns\RequiresAdmin;
use App\Models\Product;
use App\Models\StockMovement;
use App\Models\Supplier;
use App\Services\SupplierService;
use App\Services\WhatsAppService;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;
use Throwable;

#[Layout('layouts::erp')]
class SupplierDetail extends Component
{
    use RequiresAdmin, WithPagination;

    public const PER_PAGE = 15;

    /**
     * Products needing restock come first: critical, then low, then ok.
     */
    protected const STATUS_ORDER = ['critical' => 0, 'low' => 1, 'ok' => 2];

    public Supplier $supplier;

    #[Url(as: 'desde', except: '')]
    public string $dateFrom = '';

    #[Url(as: 'hasta', except: '')]
    public string $dateTo = '';

    public function mount(Supplier $supplier): void
    {
        $this->supplier = $supplier;
    }

    public function updated(string $property): void
    {
        if (in_array($property, ['dateFrom', 'dateTo'], true)) {
            $this->resetPage();
        }
    }

    public function clearDates(): void
    {
        $this->reset('dateFrom', 'dateTo');
        $this->resetPage();
    }

    /**
     * Baja lógica: products and restock history are kept, the supplier is no longer offered when loading products.
     */
    public function deactivate(SupplierService $suppliers): void
    {
        $suppliers->setActive($this->supplier, false);
        $this->dispatch('toast', message: "{$this->supplier->business_name} dado de baja.");
    }

    public function reactivate(SupplierService $suppliers): void
    {
        $suppliers->setActive($this->supplier, true);
        $this->dispatch('toast', message: "{$this->supplier->business_name} reactivado.");
    }

    /**
     * Products from this supplier, the ones with low or critical stock first and inactive ones last.
     *
     * @return Collection<int, Product>
     */
    #[Computed]
    public function products(): Collection
    {
        return $this->supplier->products()
            ->orderBy('name')
            ->get()
            ->sortBy(fn (Product $product) => [$product->is_active ? 0 : 1, self::STATUS_ORDER[$product->stock_status]])
            ->values();
    }

    /**
     * Active products that need restocking (low or critical stock).
     *
     * @return Collection<int, Product>
     */
    #[Computed]
    public function lowStockProducts(): Collection
    {
        return $this->products->filter(fn (Product $product) => $product->is_active && $product->stock_status !== 'ok')->values();
    }

    /**
     * @return array{last_restock_at: ?CarbonImmutable, month_restocks: int, month_units: int}
     */
    #[Computed]
    public function summary(): array
    {
        $month = $this->supplier->restocks()->whereBetween('created_at', [now()->startOfMonth(), now()]);
        $lastRestock = $this->supplier->restocks()->max('created_at');

        return [
            'last_restock_at' => $lastRestock ? CarbonImmutable::parse($lastRestock) : null,
            'month_restocks' => (clone $month)->count(),
            'month_units' => (int) (clone $month)->sum('quantity'),
        ];
    }

    /**
     * Restock history ("ingreso" movements with this supplier), newest first, within the date filter.
     *
     * @return LengthAwarePaginator<int, StockMovement>
     */
    #[Computed]
    public function restocks(): LengthAwarePaginator
    {
        $from = $this->parseDate($this->dateFrom);
        $to = $this->parseDate($this->dateTo);

        return $this->supplier->restocks()
            ->with(['product:id,name,sku', 'lot:id,lot_number,expiration_date', 'user:id,name'])
            ->when($from, fn ($query) => $query->where('created_at', '>=', $from->startOfDay()))
            ->when($to, fn ($query) => $query->where('created_at', '<=', $to->endOfDay()))
            ->latest()
            ->latest('id')
            ->paginate(self::PER_PAGE);
    }

    /**
     * Suggested order (same text as the WhatsApp message) for the "Copiar pedido" button.
     */
    #[Computed]
    public function orderText(): string
    {
        return app(WhatsAppService::class)->supplierOrderText($this->supplier, $this->lowStockProducts);
    }

    #[Computed]
    public function whatsappUrl(): ?string
    {
        return $this->supplier->phone
            ? app(WhatsAppService::class)->supplierContactUrl($this->supplier, $this->lowStockProducts)
            : null;
    }

    public function render()
    {
        return view('livewire.erp.supplier-detail')
            ->title($this->supplier->business_name.' · Proveedores');
    }

    protected function parseDate(string $date): ?CarbonImmutable
    {
        if (trim($date) === '') {
            return null;
        }

        try {
            return CarbonImmutable::createFromFormat('Y-m-d', $date) ?: null;
        } catch (Throwable) {
            return null;
        }
    }
}
