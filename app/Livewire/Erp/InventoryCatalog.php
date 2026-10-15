<?php

namespace App\Livewire\Erp;

use App\Models\Category;
use App\Models\Lot;
use App\Models\Product;
use App\Models\Sale;
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
