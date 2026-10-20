<?php

namespace App\Livewire\Erp;

use App\Models\Category;
use App\Models\Lot;
use App\Models\Product;
use App\Models\Sale;
use App\Services\ProductService;
use App\Support\Pricing;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\On;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts::erp')]
#[Title('Inventario & Lotes')]
class InventoryCatalog extends Component
{
    use WithPagination;

    public const PER_PAGE = 10;

    /**
     * Price percentages editable inline from the table.
     */
    public const INLINE_PERCENTS = [
        'markup_percent' => '% Remarque',
        'vat_percent' => '% IVA',
    ];

    #[Url(as: 'q', except: '')]
    public string $search = '';

    #[Url(as: 'rubro', except: null)]
    public ?int $categoryId = null;

    #[Url(as: 'orden', except: 'name')]
    public string $sort = 'name';

    public function updated(string $property): void
    {
        if (in_array($property, ['search', 'categoryId', 'sort'], true)) {
            $this->resetPage();
        }
    }

    public function setCategory(?int $categoryId): void
    {
        $this->categoryId = $categoryId;
        $this->resetPage();
    }

    public function setSort(string $sort): void
    {
        $this->sort = array_key_exists($sort, Product::SORTS) ? $sort : 'name';
        $this->resetPage();
    }

    /**
     * Refresh after a product is created/edited or its stock changes in a child modal.
     */
    #[On('product-saved')]
    #[On('stock-updated')]
    public function refreshCatalog(): void
    {
        unset($this->kpis, $this->products);
    }

    /**
     * @return array{total: int, critical_stock: int, expiring_lots: int, critical_lots: int, cash_today: float}
     */
    #[Computed]
    public function kpis(): array
    {
        $activeLots = fn () => Lot::query()
            ->where('quantity', '>', 0)
            ->whereHas('product', fn (Builder $query) => $query->where('is_active', true));

        return [
            'total' => Product::where('is_active', true)->count(),
            'critical_stock' => Product::where('is_active', true)->where('stock', '<=', Product::CRITICAL_STOCK)->count(),
            'expiring_lots' => $activeLots()->whereBetween('expiration_date', [today(), today()->addDays(60)])->count(),
            'critical_lots' => $activeLots()->whereBetween('expiration_date', [today(), today()->addDays(29)])->count(),
            'cash_today' => round((float) Sale::query()
                ->where('type', 'contado')
                ->whereBetween('created_at', [today(), today()->endOfDay()])
                ->sum('total'), 2),
        ];
    }

    /**
     * Inline edit of the markup or VAT % from the table: keeps the cost, recalculates the final price
     * and records the price history. Returns the value to show in the input, or null when rejected.
     */
    public function updatePercent(int $productId, string $field, string $value, ProductService $products): ?string
    {
        if (! array_key_exists($field, self::INLINE_PERCENTS)) {
            return null;
        }

        $product = Product::findOrFail($productId);
        $percent = Pricing::parse($value);

        if ($percent === null || $percent < ($field === 'markup_percent' ? -100 : 0)) {
            $this->dispatch('toast', message: $field === 'markup_percent'
                ? 'El remarque tiene que ser un número mayor o igual a -100.'
                : 'El IVA tiene que ser un número mayor o igual a cero.', type: 'error');

            return null;
        }

        $percent = round($percent, 2);

        if ($percent === round((float) $product->{$field}, 2)) {
            return Pricing::percent($percent);
        }

        $markup = $field === 'markup_percent' ? $percent : (float) $product->markup_percent;
        $vat = $field === 'vat_percent' ? $percent : (float) $product->vat_percent;
        $price = Pricing::finalPrice((float) $product->cost_price, $markup, $vat);

        if ($price <= 0) {
            $this->dispatch('toast', message: 'El precio final tiene que ser mayor a cero.', type: 'error');

            return null;
        }

        $products->update($product, ['markup_percent' => $markup, 'vat_percent' => $vat, 'price' => $price], auth()->id());

        unset($this->products);
        $this->dispatch('toast', message: 'Se actualizó '.$product->name.': precio final '.Pricing::money($price).'.');

        return Pricing::percent($percent);
    }

    #[Computed]
    public function categories(): Collection
    {
        return Category::orderBy('id')->get();
    }

    #[Computed]
    public function products(): LengthAwarePaginator
    {
        $sort = array_key_exists($this->sort, Product::SORTS) ? $this->sort : 'name';

        return Product::query()
            ->with(['category', 'nextLot'])
            ->inventory($this->search, $this->categoryId, $sort)
            ->paginate(self::PER_PAGE);
    }

    public function render()
    {
        return view('livewire.erp.inventory-catalog');
    }
}
