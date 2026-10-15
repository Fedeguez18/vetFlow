<?php

namespace App\Support;

/**
 * Price formula: final = cost × (1 + markup/100) × (1 + vat/100).
 */
class Pricing
{
    /**
     * Final price (with VAT) from cost, markup % and VAT %, optionally rounded to 10 or 100.
     */
    public static function finalPrice(float $cost, float $markupPercent, float $vatPercent, int $roundTo = 0): float
    {
        $final = $cost * (1 + $markupPercent / 100) * (1 + $vatPercent / 100);

        return self::round($final, $roundTo);
    }

    /**
     * Markup % that turns the cost into the given final price, keeping cost and VAT fixed.
     */
    public static function markupFromFinal(float $cost, float $vatPercent, float $finalPrice): ?float
    {
        $base = $cost * (1 + $vatPercent / 100);

        if ($base <= 0) {
            return null;
        }

        return round(($finalPrice / $base - 1) * 100, 2);
    }

    /**
     * Breakdown of the final price: cost, profit, VAT and final amounts.
     *
     * @return array{cost: float, profit: float, vat: float, final: float}
     */
    public static function breakdown(float $cost, float $vatPercent, float $finalPrice): array
    {
        $net = $finalPrice / (1 + $vatPercent / 100);

        return [
            'cost' => round($cost, 2),
            'profit' => round($net - $cost, 2),
            'vat' => round($finalPrice - $net, 2),
            'final' => round($finalPrice, 2),
        ];
    }

    /**
     * Round to cents, or to the nearest ten / hundred.
     */
    public static function round(float $amount, int $roundTo = 0): float
    {
        return $roundTo > 0 ? round($amount / $roundTo) * $roundTo : round($amount, 2);
    }

    /**
     * Parse an amount typed by the user: "1500.50", "1.500,50" or "25.000".
     */
    public static function parse(mixed $value): ?float
    {
        if (is_int($value) || is_float($value)) {
            return (float) $value;
        }

        $amount = str_replace([' ', '$', '%'], '', trim((string) $value));

        if (str_contains($amount, ',') || preg_match('/^\d{1,3}(\.\d{3})+$/', $amount)) {
            $amount = str_replace(',', '.', str_replace('.', '', $amount));
        }

        return is_numeric($amount) ? (float) $amount : null;
    }

    /**
     * Argentine currency format: "$18.200" for whole amounts, "$18,90" otherwise.
     */
    public static function money(mixed $amount): string
    {
        $amount = round((float) $amount, 2);
        $decimals = fmod(abs($amount), 1) < 0.005 ? 0 : 2;

        return ($amount < 0 ? '-' : '').'$'.number_format(abs($amount), $decimals, ',', '.');
    }

    /**
     * Percent with comma decimals only when needed: "21", "10,5".
     */
    public static function percent(mixed $value): string
    {
        $value = round((float) $value, 2);

        return rtrim(rtrim(number_format($value, 2, ',', '.'), '0'), ',');
    }
}
