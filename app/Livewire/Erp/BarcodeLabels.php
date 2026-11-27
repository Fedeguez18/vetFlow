<?php

namespace App\Livewire\Erp;

use App\Livewire\Concerns\RequiresAdmin;
use App\Models\BarcodeLabel;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Pending label sheet: products that got an invented barcode, how many stickers each, print and clear.
 */
#[Layout('layouts::erp')]
#[Title('Etiquetas')]
class BarcodeLabels extends Component
{
    use RequiresAdmin;

    public function updateCopies(int $labelId, mixed $copies): void
    {
        $label = BarcodeLabel::findOrFail($labelId);
        $copies = filter_var($copies, FILTER_VALIDATE_INT);

        if ($copies === false || $copies < 1) {
            unset($this->labels);
            $this->dispatch('toast', message: 'La cantidad tiene que ser 1 o más.', type: 'error');

            return;
        }

        $label->update(['copies' => min(BarcodeLabel::MAX_COPIES, $copies)]);
        unset($this->labels);
    }

    public function remove(int $labelId): void
    {
        BarcodeLabel::whereKey($labelId)->delete();
        unset($this->labels);
    }

    /**
     * After printing: the stickers are on the products, the sheet starts empty again.
     */
    public function clear(): void
    {
        BarcodeLabel::query()->delete();
        unset($this->labels);
        $this->dispatch('toast', message: 'Hoja de etiquetas vaciada.');
    }

    /**
     * @return Collection<int, BarcodeLabel>
     */
    #[Computed]
    public function labels(): Collection
    {
        return BarcodeLabel::with('product:id,name,barcode,sku,stock')->oldest()->oldest('id')->get();
    }

    public function render()
    {
        $total = (int) $this->labels->sum('copies');

        return view('livewire.erp.barcode-labels', [
            'total' => $total,
            'sheets' => (int) ceil($total / BarcodeLabel::PER_SHEET),
        ]);
    }
}
