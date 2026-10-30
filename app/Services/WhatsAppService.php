<?php

namespace App\Services;

use App\Models\Producer;
use App\Models\Product;
use App\Models\Sale;
use App\Models\Supplier;
use App\Support\Pricing;
use Illuminate\Support\Collection;

class WhatsAppService
{
    /**
     * wa.me link with the producer's balance and unpaid remitos, addressed to their phone when available.
     *
     * @param  Collection<int, Sale>  $unpaidRemitos
     */
    public function balanceReminderUrl(Producer $producer, Collection $unpaidRemitos): string
    {
        $lines = [
            "Hola {$producer->name}, le escribimos de la veterinaria.",
            $producer->hasCreditBalance()
                ? 'Al '.today()->format('d/m/Y').' tiene un saldo a favor en cuenta corriente de *'.Pricing::moneyWithCents(-(float) $producer->balance).'*.'
                : 'Su saldo en cuenta corriente al '.today()->format('d/m/Y').' es de *'.Pricing::moneyWithCents($producer->balance).'*.',
        ];

        if ($unpaidRemitos->isNotEmpty()) {
            $lines[] = '';
            $lines[] = 'Remitos impagos:';

            foreach ($unpaidRemitos as $sale) {
                $line = "• {$sale->voucher_number} del {$sale->created_at->format('d/m/Y')}: ".Pricing::moneyWithCents((float) $sale->total - (float) $sale->paid);

                if ($sale->status === 'parcial') {
                    $line .= ' (pendiente de un total de '.Pricing::moneyWithCents($sale->total).')';
                }

                $lines[] = $line;
            }
        }

        $lines[] = '';
        $lines[] = 'Quedamos a disposición por cualquier consulta. ¡Muchas gracias!';

        return 'https://wa.me/'.($this->internationalPhone($producer->phone) ?? '').'?text='.rawurlencode(implode("\n", $lines));
    }

    /**
     * wa.me link to the supplier's phone with the suggested order when there are products to restock.
     *
     * @param  Collection<int, Product>  $lowStock
     */
    public function supplierContactUrl(Supplier $supplier, Collection $lowStock): string
    {
        return 'https://wa.me/'.($this->internationalPhone($supplier->phone) ?? '').'?text='.rawurlencode($this->supplierOrderText($supplier, $lowStock));
    }

    /**
     * Message for the supplier (addressed to the contact when loaded) listing the suggested quantity of
     * each product to restock. Also used by the "Copiar pedido" button.
     *
     * @param  Collection<int, Product>  $lowStock
     */
    public function supplierOrderText(Supplier $supplier, Collection $lowStock): string
    {
        $lines = ['Hola '.($supplier->contact_name ?: $supplier->business_name).', les escribimos de la veterinaria.'];

        if ($lowStock->isNotEmpty()) {
            $lines[] = 'Queremos hacer el siguiente pedido:';

            foreach ($lowStock as $product) {
                $lines[] = "• {$product->suggestedRestock()} u. {$product->name}";
            }

            $lines[] = '';
            $lines[] = '¡Muchas gracias!';
        }

        return implode("\n", $lines);
    }

    /**
     * Normalize an Argentine phone number to the international format WhatsApp expects (549 + area + number).
     */
    public function internationalPhone(?string $phone): ?string
    {
        $digits = preg_replace('/\D+/', '', (string) $phone);

        if ($digits === '') {
            return null;
        }

        return str_starts_with($digits, '54') ? $digits : '549'.ltrim($digits, '0');
    }
}
