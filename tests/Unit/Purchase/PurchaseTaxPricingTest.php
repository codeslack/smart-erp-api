<?php

namespace Tests\Unit\Purchase;

use Tests\TestCase;

use App\Modules\Purchase\Services\Calculators\PurchaseCalculator;
use App\Modules\Purchase\Services\Calculators\PurchaseItemCalculator;

class PurchaseTaxPricingTest extends TestCase
{
    public function test_inclusive_unit_cost_extracts_tax_before_document_totals(): void
    {
        $item = app(PurchaseItemCalculator::class)->calculate([
            'quantity' => 2,
            'unit_cost' => 110,
            'tax_rate' => 10,
        ], 'inclusive');

        $this->assertEquals(200, $item['line_total']);
        $this->assertEquals(200, $item['subtotal']);
        $this->assertEquals(20, $item['tax_amount']);
        $this->assertEquals(220, $item['total_amount']);
    }

    public function test_exclusive_unit_cost_remains_tax_exclusive(): void
    {
        $item = app(PurchaseItemCalculator::class)->calculate([
            'quantity' => 2,
            'unit_cost' => 110,
            'tax_rate' => 10,
        ], 'exclusive');

        $this->assertEquals(220, $item['line_total']);
        $this->assertEquals(220, $item['subtotal']);
        $this->assertEquals(22, $item['tax_amount']);
        $this->assertEquals(242, $item['total_amount']);
    }

    public function test_inclusive_item_discount_reduces_taxable_base_and_tax(): void
    {
        $item = app(PurchaseItemCalculator::class)->calculate([
            'quantity' => 2,
            'unit_cost' => 110,
            'discount_type' => 'fixed',
            'discount_amount' => 10,
            'tax_rate' => 10,
        ], 'inclusive');

        $this->assertEquals(200, $item['line_total']);
        $this->assertEquals(10, $item['discount_amount']);
        $this->assertEquals(190, $item['subtotal']);
        $this->assertEquals(19, $item['tax_amount']);
        $this->assertEquals(209, $item['total_amount']);
    }

    public function test_header_discount_is_allocated_and_reduces_item_tax(): void
    {
        $calculator = app(PurchaseCalculator::class);

        $items = [
            [
                'line_total' => 200,
                'subtotal' => 200,
                'discount_amount' => 0,
                'tax_rate' => 10,
                'tax_amount' => 20,
                'total_amount' => 220,
            ],
        ];

        $header = [
            'discount_type' => 'fixed',
            'discount_rate' => 0,
            'discount_amount' => 10,
        ];

        $adjustedItems = $calculator->applyHeaderDiscount(
            $items,
            $header
        );

        $this->assertEquals(190, $adjustedItems[0]['subtotal']);
        $this->assertEquals(19, $adjustedItems[0]['tax_amount']);
        $this->assertEquals(209, $adjustedItems[0]['total_amount']);

        $totals = $calculator->calculateTotals(
            $adjustedItems,
            $header
        );

        $this->assertEquals(200, $totals['subtotal']);
        $this->assertEquals(10, $totals['discount_amount']);
        $this->assertEquals(19, $totals['tax_amount']);
        $this->assertEquals(209, $totals['grand_total']);
    }
}
