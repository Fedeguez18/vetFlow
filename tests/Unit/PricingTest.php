<?php

namespace Tests\Unit;

use App\Support\Pricing;
use PHPUnit\Framework\TestCase;

class PricingTest extends TestCase
{
    public function test_final_price_formula_and_its_inverse(): void
    {
        $this->assertSame(1815.0, Pricing::finalPrice(1000, 50, 21));
        $this->assertSame(1657.5, Pricing::finalPrice(1000, 50, 10.5));
        $this->assertSame(1500.0, Pricing::finalPrice(1000, 50, 0));
        $this->assertSame(50.0, Pricing::markupFromFinal(1000, 21, 1815));
        $this->assertSame(1800.0, Pricing::finalPrice(1000, 47, 21, 100));
        $this->assertSame(1780.0, Pricing::finalPrice(1000, 47, 21, 10));
        $this->assertNull(Pricing::markupFromFinal(0, 21, 100));
    }
}
