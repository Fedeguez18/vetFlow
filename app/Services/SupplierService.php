<?php

namespace App\Services;

use App\Exceptions\CatalogException;
use App\Models\StockMovement;
use App\Models\Supplier;

class SupplierService
{
    /**
     * Create a supplier, or update it when one is given.
     *
     * @param  array<string, ?string>  $attributes  business_name plus the optional contact, tax and payment fields.
     */
    public function save(array $attributes, ?Supplier $supplier = null): Supplier
    {
        if ($supplier) {
            $supplier->update($attributes);

            return $supplier;
        }

        return Supplier::create($attributes);
    }

    /**
     * @throws CatalogException when products or restock history still reference the supplier.
     */
    public function delete(Supplier $supplier): void
    {
        $products = $supplier->products()->count();

        if ($products > 0) {
            throw new CatalogException("No se puede eliminar «{$supplier->business_name}»: tiene {$products} productos asignados. Podés darlo de baja para que no aparezca más al cargar artículos.");
        }

        if (StockMovement::where('supplier_id', $supplier->id)->exists()) {
            throw new CatalogException("No se puede eliminar «{$supplier->business_name}»: figura en el historial de reposiciones. Podés darlo de baja para que no aparezca más al cargar artículos.");
        }

        $supplier->delete();
    }

    /**
     * Baja lógica (or reactivation): products and restock history keep pointing to the supplier.
     */
    public function setActive(Supplier $supplier, bool $active): void
    {
        $supplier->update(['is_active' => $active]);
    }

    /**
     * Accepts "30712345671" or "30-71234567-1" and returns the dashed form; anything else is returned trimmed.
     */
    public function normalizeCuit(string $cuit): string
    {
        $digits = preg_replace('/\D/', '', $cuit);

        if (strlen($digits) === 11) {
            return substr($digits, 0, 2).'-'.substr($digits, 2, 8).'-'.substr($digits, 10);
        }

        return trim($cuit);
    }
}
