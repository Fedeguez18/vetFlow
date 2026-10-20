<?php

namespace Tests\Unit;

use App\Support\AmountInWords;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class AmountInWordsTest extends TestCase
{
    /**
     * @return array<string, array{int, string}>
     */
    public static function numbers(): array
    {
        return [
            'cero' => [0, 'cero'],
            'uno' => [1, 'uno'],
            'dieciséis' => [16, 'dieciséis'],
            'veintiuno' => [21, 'veintiuno'],
            'treinta y cinco' => [35, 'treinta y cinco'],
            'cien' => [100, 'cien'],
            'ciento uno' => [101, 'ciento uno'],
            'quinientos' => [500, 'quinientos'],
            'mil' => [1000, 'mil'],
            'veintiún mil' => [21000, 'veintiún mil'],
            'treinta y un mil' => [31000, 'treinta y un mil'],
            'ciento un mil' => [101000, 'ciento un mil'],
            'trescientos mil' => [300000, 'trescientos mil'],
            'un millón' => [1000000, 'un millón'],
            'dos millones y pico' => [2500100, 'dos millones quinientos mil cien'],
            'veintiún millones' => [21000000, 'veintiún millones'],
            'mil quinientos millones' => [1500000000, 'mil quinientos millones'],
        ];
    }

    #[DataProvider('numbers')]
    public function test_numbers_are_written_in_spanish(int $number, string $words): void
    {
        $this->assertSame($words, AmountInWords::number($number));
    }

    public function test_money_adds_the_cents(): void
    {
        $this->assertSame('ciento veintidós mil ciento ochenta y uno con 57/100', AmountInWords::money(122181.57));
        $this->assertSame('trescientos mil con 00/100', AmountInWords::money(300000));
        $this->assertSame('cero con 05/100', AmountInWords::money(0.05));
    }
}
