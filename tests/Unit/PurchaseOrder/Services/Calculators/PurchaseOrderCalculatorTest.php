<?php

namespace Tests\Unit\PurchaseOrder\Services\Calculators;

use App\Modules\PurchaseOrder\Services\Calculators\PurchaseOrderCalculator;
use PHPUnit\Framework\TestCase;

class PurchaseOrderCalculatorTest extends TestCase
{
    private PurchaseOrderCalculator $calculator;

    protected function setUp(): void
    {
        parent::setUp();

        $this->calculator = new PurchaseOrderCalculator();
    }

    public function test_calculates_header_totals_from_items(): void
    {
        $result = $this->calculator->calculate([
            [
                'subtotal_amount' => 4800,
                'discount_amount' => 200,
                'tax_total_amount' => 0,
            ],
            [
                'subtotal_amount' => 9000,
                'discount_amount' => 1000,
                'tax_total_amount' => 0,
            ],
        ]);

        $this->assertSame(13800.0, $result['subtotal']);
        $this->assertSame(1200.0, $result['discount']);
        $this->assertSame(0.0, $result['additional_charge']);
        $this->assertSame(0.0, $result['tax_total']);
        $this->assertSame(0.0, $result['rounding_adjustment']);
        $this->assertSame(13800.0, $result['grand_total']);
    }

    public function test_calculates_tax_and_additional_charge(): void
    {
        $result = $this->calculator->calculate(
            [
                [
                    'subtotal_amount' => 10000,
                    'discount_amount' => 500,
                    'tax_total_amount' => 1710,
                ],
                [
                    'subtotal_amount' => 5000,
                    'discount_amount' => 0,
                    'tax_total_amount' => 900,
                ],
            ],
            1000
        );

        $this->assertSame(15000.0, $result['subtotal']);
        $this->assertSame(500.0, $result['discount']);
        $this->assertSame(2610.0, $result['tax_total']);
        $this->assertSame(1000.0, $result['additional_charge']);
        $this->assertSame(18610.0, $result['grand_total']);
    }

    public function test_applies_rounding_adjustment(): void
    {
        $result = $this->calculator->calculate(
            [
                [
                    'subtotal_amount' => 10000,
                    'discount_amount' => 0,
                    'tax_total_amount' => 1800,
                ],
            ],
            0,
            0.25
        );

        $this->assertSame(10000.0, $result['subtotal']);
        $this->assertSame(1800.0, $result['tax_total']);
        $this->assertSame(0.25, $result['rounding_adjustment']);
        $this->assertSame(11800.25, $result['grand_total']);
    }

    public function test_handles_empty_items(): void
    {
        $result = $this->calculator->calculate([]);

        $this->assertSame(0.0, $result['subtotal']);
        $this->assertSame(0.0, $result['discount']);
        $this->assertSame(0.0, $result['additional_charge']);
        $this->assertSame(0.0, $result['tax_total']);
        $this->assertSame(0.0, $result['rounding_adjustment']);
        $this->assertSame(0.0, $result['grand_total']);
    }

    public function test_handles_missing_optional_item_values(): void
    {
        $result = $this->calculator->calculate([
            [
                'subtotal_amount' => 5000,
            ],
            [
                'subtotal_amount' => 2500,
                'tax_total_amount' => 450,
            ],
        ]);

        $this->assertSame(7500.0, $result['subtotal']);
        $this->assertSame(0.0, $result['discount']);
        $this->assertSame(450.0, $result['tax_total']);
        $this->assertSame(7950.0, $result['grand_total']);
    }

    public function test_supports_negative_rounding_adjustment(): void
    {
        $result = $this->calculator->calculate(
            [
                [
                    'subtotal_amount' => 10000,
                    'discount_amount' => 0,
                    'tax_total_amount' => 1800,
                ],
            ],
            0,
            -0.25
        );

        $this->assertSame(-0.25, $result['rounding_adjustment']);
        $this->assertSame(11799.75, $result['grand_total']);
    }
}
