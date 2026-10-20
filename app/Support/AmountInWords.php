<?php

namespace App\Support;

/**
 * Amounts written in Spanish words for receipts: 122000.5 → "ciento veintidós mil con 50/100".
 */
class AmountInWords
{
    protected const UNITS = [
        'cero', 'uno', 'dos', 'tres', 'cuatro', 'cinco', 'seis', 'siete', 'ocho', 'nueve',
        'diez', 'once', 'doce', 'trece', 'catorce', 'quince', 'dieciséis', 'diecisiete', 'dieciocho', 'diecinueve',
        'veinte', 'veintiuno', 'veintidós', 'veintitrés', 'veinticuatro', 'veinticinco', 'veintiséis', 'veintisiete', 'veintiocho', 'veintinueve',
    ];

    protected const TENS = [3 => 'treinta', 4 => 'cuarenta', 5 => 'cincuenta', 6 => 'sesenta', 7 => 'setenta', 8 => 'ochenta', 9 => 'noventa'];

    protected const HUNDREDS = [1 => 'ciento', 2 => 'doscientos', 3 => 'trescientos', 4 => 'cuatrocientos', 5 => 'quinientos', 6 => 'seiscientos', 7 => 'setecientos', 8 => 'ochocientos', 9 => 'novecientos'];

    /**
     * "trescientos mil con 00/100".
     */
    public static function money(float $amount): string
    {
        $cents = (int) round(abs($amount) * 100);

        return self::number(intdiv($cents, 100)).' con '.sprintf('%02d', $cents % 100).'/100';
    }

    public static function number(int $number): string
    {
        if ($number === 0) {
            return 'cero';
        }

        $millions = intdiv($number, 1_000_000);
        $thousands = intdiv($number % 1_000_000, 1000);
        $rest = $number % 1000;

        $words = [];

        if ($millions > 0) {
            $words[] = $millions === 1 ? 'un millón' : self::apocope(self::number($millions)).' millones';
        }

        if ($thousands > 0) {
            $words[] = $thousands === 1 ? 'mil' : self::apocope(self::hundreds($thousands)).' mil';
        }

        if ($rest > 0) {
            $words[] = self::hundreds($rest);
        }

        return implode(' ', $words);
    }

    protected static function hundreds(int $number): string
    {
        if ($number === 100) {
            return 'cien';
        }

        $hundreds = intdiv($number, 100);
        $rest = $number % 100;

        return trim(($hundreds > 0 ? self::HUNDREDS[$hundreds] : '').' '.($rest > 0 ? self::tens($rest) : ''));
    }

    protected static function tens(int $number): string
    {
        if ($number < 30) {
            return self::UNITS[$number];
        }

        $units = $number % 10;

        return self::TENS[intdiv($number, 10)].($units > 0 ? ' y '.self::UNITS[$units] : '');
    }

    /**
     * "uno" shortens before "mil" / "millones": veintiún mil, ciento un millones.
     */
    protected static function apocope(string $words): string
    {
        return match (true) {
            str_ends_with($words, 'veintiuno') => substr($words, 0, -strlen('veintiuno')).'veintiún',
            str_ends_with($words, 'uno') => substr($words, 0, -strlen('uno')).'un',
            default => $words,
        };
    }
}
