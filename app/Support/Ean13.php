<?php

namespace App\Support;

use App\Models\Product;

/**
 * EAN-13 codes invented by the store for products that come without one, and their bars as SVG.
 * Prefix 20 is the GS1 range reserved for in-store use, so it never clashes with a manufacturer's code.
 */
class Ean13
{
    public const INTERNAL_PREFIX = '20';

    /**
     * Left-hand odd parity (L) patterns; G is L reversed and inverted, R is L inverted.
     *
     * @var array<int, string>
     */
    protected const L = ['0001101', '0011001', '0010011', '0111101', '0100011', '0110001', '0101111', '0111011', '0110111', '0001011'];

    /**
     * L/G pattern of the left half, chosen by the first digit (which is not drawn as bars).
     *
     * @var array<int, string>
     */
    protected const PARITY = ['LLLLLL', 'LLGLGG', 'LLGGLG', 'LLGGGL', 'LGLLGG', 'LGGLLG', 'LGGGLG', 'LGLGGL', 'LGLGLG', 'LGGLGL'];

    /**
     * The next free internal code: 20 + a 10-digit sequence + check digit.
     */
    public static function nextInternal(): string
    {
        $last = Product::query()
            ->where('barcode', 'like', self::INTERNAL_PREFIX.'%')
            ->whereRaw('length(barcode) = 13')
            ->orderByDesc('barcode')
            ->value('barcode');

        $sequence = $last !== null && ctype_digit($last) ? (int) substr($last, 2, 10) + 1 : 1;

        do {
            $body = self::INTERNAL_PREFIX.str_pad((string) $sequence++, 10, '0', STR_PAD_LEFT);
            $code = $body.self::checkDigit($body);
        } while (Product::query()->where('barcode', $code)->exists());

        return $code;
    }

    public static function checkDigit(string $twelveDigits): int
    {
        $sum = 0;

        foreach (str_split($twelveDigits) as $position => $digit) {
            $sum += (int) $digit * ($position % 2 === 0 ? 1 : 3);
        }

        return (10 - $sum % 10) % 10;
    }

    public static function isValid(?string $code): bool
    {
        return $code !== null
            && strlen($code) === 13
            && ctype_digit($code)
            && self::checkDigit(substr($code, 0, 12)) === (int) $code[12];
    }

    /**
     * The 95 modules of the symbol: 1 = bar, 0 = space.
     */
    public static function modules(string $code): string
    {
        $digits = array_map('intval', str_split($code));
        $parity = self::PARITY[$digits[0]];
        $modules = '101';

        for ($i = 1; $i <= 6; $i++) {
            $pattern = self::L[$digits[$i]];
            $modules .= $parity[$i - 1] === 'L' ? $pattern : strrev(strtr($pattern, '01', '10'));
        }

        $modules .= '01010';

        for ($i = 7; $i <= 12; $i++) {
            $modules .= strtr(self::L[$digits[$i]], '01', '10');
        }

        return $modules.'101';
    }

    /**
     * Bars as an inline SVG that stretches to its container, with the quiet zones included.
     */
    public static function svg(string $code): string
    {
        $quietLeft = 11;
        $width = $quietLeft + 95 + 7;
        $height = 50;
        $rects = '';

        foreach (self::runs(self::modules($code)) as [$start, $length]) {
            $rects .= '<rect x="'.($quietLeft + $start).'" y="0" width="'.$length.'" height="'.$height.'"/>';
        }

        return '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 '.$width.' '.$height.'" preserveAspectRatio="none" shape-rendering="crispEdges" fill="#000">'.$rects.'</svg>';
    }

    /**
     * Consecutive bars merged into [start, length] pairs.
     *
     * @return array<int, array{0: int, 1: int}>
     */
    protected static function runs(string $modules): array
    {
        preg_match_all('/1+/', $modules, $matches, PREG_OFFSET_CAPTURE);

        return array_map(fn (array $match) => [$match[1], strlen($match[0])], $matches[0]);
    }
}
