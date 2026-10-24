<?php

namespace Tests\Unit\PurchaseOrder\Services\Calculators;

use App\Modules\PurchaseOrder\Services\Calculators\PurchaseOrderItemCalculator;
use PHPUnit\Framework\TestCase;

class PurchaseOrderItemCalculatorTest extends TestCase
{
    private PurchaseOrderItemCalculator $calculator;

    protected function setUp(): void
    {
        parent::setUp();

        $this->calculator = new PurchaseOrderItemCalculator();
    }

    public function test_calculates_fixed_discount(): void
    {
        $result = $this->calculator->calculate([
            'quantity' => 10,
            'unit_price' => 500,
            'discount_type' => 'fixed',
            'discount_rate' => 200,
        ]);

        $this->assertSame(200.0, $result['discount_amount']);
        $this->assertSame(480.0, $result['net_unit_price']);
        $this->assertSame(4800.0, $result['subtotal_amount']);
        $this->assertSame(4800.0, $result['tax_base_amount']);
        $this->assertSame(0.0, $result['tax_total_amount']);
        $this->assertSame(4800.0, $result['total_amount']);
    }

    public function test_calculates_percentage_discount(): void
    {
        $result = $this->calculator->calculate([
            'quantity' => 10,
            'unit_price' => 500,
            'discount_type' => 'percentage',
            'discount_rate' => 10,
        ]);

        $this->assertSame(500.0, $result['discount_amount']);
        $this->assertSame(450.0, $result['net_unit_price']);
        $this->assertSame(4500.0, $result['subtotal_amount']);
        $this->assertSame(4500.0, $result['tax_base_amount']);
        $this->assertSame(0.0, $result['tax_total_amount']);
        $this->assertSame(4500.0, $result['total_amount']);
    }

    public function test_fixed_discount_cannot_exceed_gross_amount(): void
    {
        $result = $this->calculator->calculate([
            'quantity' => 2,
            'unit_price' => 100,
            'discount_type' => 'fixed',
            'discount_rate' => 500,
        ]);

        $this->assertSame(200.0, $result['discount_amount']);
        $this->assertSame(0.0, $result['net_unit_price']);
        $this->assertSame(0.0, $result['subtotal_amount']);
        $this->assertSame(0.0, $result['tax_base_amount']);
        $this->assertSame(0.0, $result['total_amount']);
    }

    public function test_zero_discount_keeps_gross_amount(): void
    {
        $result = $this->calculator->calculate([
            'quantity' => 5,
            'unit_price' => 1250,
            'discount_type' => 'fixed',
            'discount_rate' => 0,
        ]);

        $this->assertSame(0.0, $result['discount_amount']);
        $this->assertSame(1250.0, $result['net_unit_price']);
        $this->assertSame(6250.0, $result['subtotal_amount']);
        $this->assertSame(6250.0, $result['tax_base_amount']);
        $this->assertSame(0.0, $result['tax_total_amount']);
        $this->assertSame(6250.0, $result['total_amount']);
    }

    public function test_handles_decimal_quantity_and_price(): void
    {
        $result = $this->calculator->calculate([
            'quantity' => 2.5,
            'unit_price' => 125.75,
            'discount_type' => 'percentage',
            'discount_rate' => 10,
        ]);

        $this->assertSame(31.4375, $result['discount_amount']);
        $this->assertSame(113.175, $result['net_unit_price']);
        $this->assertSame(282.9375, $result['subtotal_amount']);
        $this->assertSame(282.9375, $result['tax_base_amount']);
        $this->assertSame(0.0, $result['tax_total_amount']);
        $this->assertSame(282.9375, $result['total_amount']);
    }

    public function test_tax_is_zero_until_tax_module_is_integrated(): void
    {
        $result = $this->calculator->calculate([
            'quantity' => 2,
            'unit_price' => 1000,
            'discount_type' => 'fixed',
            'discount_rate' => 100,
            'tax_group_id' => 999,
        ]);

        $this->assertSame(1900.0, $result['subtotal_amount']);
        $this->assertSame(1900.0, $result['tax_base_amount']);
        $this->assertSame(0.0, $result['tax_total_amount']);
        $this->assertSame(1900.0, $result['total_amount']);
    }
}