<?php

namespace App\Support;

/**
 * Quantities of products sold by weight are stored as integer grams; everything else, as units.
 */
class Quantity
{
    public const GRAMS_PER_KG = 1000;

    /**
     * "3" for units; "350 g" or "2,5 kg" for weight.
     */
    public static function format(int $quantity, bool $byWeight): string
    {
        if (! $byWeight) {
            return number_format($quantity, 0, ',', '.');
        }

        if (abs($quantity) < self::GRAMS_PER_KG) {
            return $quantity.' g';
        }

        return self::kilos($quantity).' kg';
    }

    /**
     * Grams as kilos without trailing zeros: 2500 → "2,5", 12000 → "12".
     */
    public static function kilos(int $grams): string
    {
        return rtrim(rtrim(number_format($grams / self::GRAMS_PER_KG, 3, ',', '.'), '0'), ',');
    }

    /**
     * Kilos typed by the user ("2,5", "0.25", "12") as grams, or null when it is not a number.
     */
    public static function gramsFromKilos(mixed $kilos): ?int
    {
        $value = Pricing::parse($kilos);

        return $value === null ? null : (int) round($value * self::GRAMS_PER_KG);
    }

    /**
     * Amount of a line: the price is per unit, or per kg when the quantity is in grams.
     */
    public static function lineTotal(float $price, int $quantity, bool $byWeight): float
    {
        return round($price * $quantity / ($byWeight ? self::GRAMS_PER_KG : 1), 2);
    }
}
