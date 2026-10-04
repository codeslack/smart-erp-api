<?php

namespace Tests\Unit\Tax;

use App\Modules\Tax\Domain\TaxPostingLine;
use App\Modules\Tax\Services\TaxPostingAggregationService;
use Tests\TestCase;

class TaxPostingAggregationServiceTest extends TestCase
{
    public function test_it_aggregates_matching_tax_posting_lines(): void
    {
        $service = new TaxPostingAggregationService();

        $lines = [
            new TaxPostingLine(
                taxComponentId: 1,
                accountId: 1210,
                direction: 'INPUT',
                amount: 4500,
            ),
            new TaxPostingLine(
                taxComponentId: 1,
                accountId: 1210,
                direction: 'INPUT',
                amount: 1800,
            ),
            new TaxPostingLine(
                taxComponentId: 1,
                accountId: 1210,
                direction: 'INPUT',
                amount: 300,
            ),
        ];

        $result = $service->aggregate($lines);

        $this->assertCount(1, $result);

        $this->assertSame(1, $result[0]->taxComponentId);
        $this->assertSame(1210, $result[0]->accountId);
        $this->assertSame('INPUT', $result[0]->direction);
        $this->assertSame(6600.0, $result[0]->amount);
    }

    public function test_it_keeps_different_tax_components_separate(): void
    {
        $service = new TaxPostingAggregationService();

        $lines = [
            new TaxPostingLine(
                taxComponentId: 1,
                accountId: 1210,
                direction: 'INPUT',
                amount: 4500,
            ),
            new TaxPostingLine(
                taxComponentId: 2,
                accountId: 1210,
                direction: 'INPUT',
                amount: 4500,
            ),
        ];

        $result = $service->aggregate($lines);

        $this->assertCount(2, $result);

        $this->assertSame(1, $result[0]->taxComponentId);
        $this->assertSame(2, $result[1]->taxComponentId);
    }

    public function test_it_keeps_different_accounts_separate(): void
    {
        $service = new TaxPostingAggregationService();

        $lines = [
            new TaxPostingLine(
                taxComponentId: 1,
                accountId: 1210,
                direction: 'INPUT',
                amount: 4500,
            ),
            new TaxPostingLine(
                taxComponentId: 1,
                accountId: 1220,
                direction: 'INPUT',
                amount: 300,
            ),
        ];

        $result = $service->aggregate($lines);

        $this->assertCount(2, $result);

        $this->assertSame(1210, $result[0]->accountId);
        $this->assertSame(1220, $result[1]->accountId);
    }

    public function test_it_keeps_different_directions_separate(): void
    {
        $service = new TaxPostingAggregationService();

        $lines = [
            new TaxPostingLine(
                taxComponentId: 1,
                accountId: 1210,
                direction: 'INPUT',
                amount: 4500,
            ),
            new TaxPostingLine(
                taxComponentId: 1,
                accountId: 1210,
                direction: 'OUTPUT',
                amount: 4500,
            ),
        ];

        $result = $service->aggregate($lines);

        $this->assertCount(2, $result);

        $this->assertSame('INPUT', $result[0]->direction);
        $this->assertSame('OUTPUT', $result[1]->direction);
    }

    public function test_it_returns_empty_array_for_empty_input(): void
    {
        $service = new TaxPostingAggregationService();

        $result = $service->aggregate([]);

        $this->assertSame([], $result);
    }

    public function test_it_supports_mixed_tax_invoice_postings(): void
    {
        $service = new TaxPostingAggregationService();

        /*
         * Purchase:
         *
         * Laptop  -> 18% -> CGST 4500 + SGST 4500
         * Mouse    -> 0%  -> no tax posting
         * Cable    -> 0%  -> no tax posting
         * Printer  -> 18% -> CGST 1800 + SGST 1800
         * Ink      -> 12% -> CGST 300  + SGST 300
         */

        $lines = [
            new TaxPostingLine(
                taxComponentId: 1,
                accountId: 1210,
                direction: 'INPUT',
                amount: 4500,
            ),
            new TaxPostingLine(
                taxComponentId: 2,
                accountId: 1210,
                direction: 'INPUT',
                amount: 4500,
            ),
            new TaxPostingLine(
                taxComponentId: 1,
                accountId: 1210,
                direction: 'INPUT',
                amount: 1800,
            ),
            new TaxPostingLine(
                taxComponentId: 2,
                accountId: 1210,
                direction: 'INPUT',
                amount: 1800,
            ),
            new TaxPostingLine(
                taxComponentId: 1,
                accountId: 1210,
                direction: 'INPUT',
                amount: 300,
            ),
            new TaxPostingLine(
                taxComponentId: 2,
                accountId: 1210,
                direction: 'INPUT',
                amount: 300,
            ),
        ];

        $result = $service->aggregate($lines);

        $this->assertCount(2, $result);

        $this->assertSame(1, $result[0]->taxComponentId);
        $this->assertSame(6600.0, $result[0]->amount);

        $this->assertSame(2, $result[1]->taxComponentId);
        $this->assertSame(6600.0, $result[1]->amount);

        $this->assertSame(
            13200.0,
            array_sum(
                array_map(
                    fn (TaxPostingLine $line): float => $line->amount,
                    $result,
                ),
            ),
        );
    }
}