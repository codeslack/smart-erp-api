<?php

namespace Tests\Feature\Tax;

use App\Modules\Accounting\Enums\AccountingAccounts;
use App\Modules\Accounting\Models\ChartOfAccount;
use App\Modules\Accounting\Services\AccountingSetupService;
use App\Modules\Tax\Domain\TaxCalculationInput;
use App\Modules\Tax\Enums\TaxComponentTypeEnum;
use App\Modules\Tax\Enums\TaxDirectionEnum;
use App\Modules\Tax\Enums\TaxPricingModeEnum;
use App\Modules\Tax\Enums\TaxRoundingModeEnum;
use App\Modules\Tax\Models\TaxAccountingMapping;
use App\Modules\Tax\Models\TaxComponent;
use App\Modules\Tax\Models\TaxGroup;
use App\Modules\Tax\Services\TaxCalculationService;
use App\Modules\Tax\Services\TaxPostingAggregationService;
use App\Modules\Tax\Services\TaxPostingService;
use App\Modules\Tax\Services\TaxSetupService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreatesTenant;
use Tests\TestCase;

class TaxPostingMixedRateIntegrationTest extends TestCase
{
    use RefreshDatabase;
    use CreatesTenant;

    protected $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = $this->createTestTenant();

        app(AccountingSetupService::class)
            ->setup($this->tenant);

        app(TaxSetupService::class)
            ->setup($this->tenant);
    }

    public function test_it_aggregates_mixed_tax_rates_from_multiple_invoice_lines(): void
    {
        $gst18 = $this->taxGroup('GST18-INTRA-IN');
        $gst0 = $this->taxGroup('GST0-INTRA-IN');
        $gst12 = $this->taxGroup('GST12-INTRA-IN');

        $calculationDate = $gst18->effective_from->toDateString();

        $cgst = $this->makeComponent(
            TaxComponentTypeEnum::CGST,
        );

        $sgst = $this->makeComponent(
            TaxComponentTypeEnum::SGST,
        );

        $accountId = $this->inputTaxAccountId();

        $this->createMapping(
            component: $cgst,
            accountId: $accountId,
            effectiveFrom: $calculationDate,
        );

        $this->createMapping(
            component: $sgst,
            accountId: $accountId,
            effectiveFrom: $calculationDate,
        );

        $calculationService = app(TaxCalculationService::class);
        $postingService = app(TaxPostingService::class);
        $aggregationService = app(TaxPostingAggregationService::class);

        /*
         * Purchase-001
         *
         * Laptop   = 50,000 @ 18%
         * Mouse    =  1,000 @  0%
         * Cable    =  2,000 @  0%
         * Printer  = 20,000 @ 18%
         * Ink      =  5,000 @ 12%
         *
         * Taxable = 78,000
         * Taxable tax = 13,200
         * Total = 91,200
         */

        $invoiceLines = [
            [
                'group' => $gst18,
                'amount' => 50000,
            ],
            [
                'group' => $gst0,
                'amount' => 1000,
            ],
            [
                'group' => $gst0,
                'amount' => 2000,
            ],
            [
                'group' => $gst18,
                'amount' => 20000,
            ],
            [
                'group' => $gst12,
                'amount' => 5000,
            ],
        ];

        $postingLines = [];
        $totalTaxable = 0.0;
        $totalTax = 0.0;
        $totalAmount = 0.0;

        foreach ($invoiceLines as $line) {
            $calculation = $calculationService->calculate(
                new TaxCalculationInput(
                    taxGroupId: $line['group']->id,
                    taxableAmount: $line['amount'],
                    pricingMode: TaxPricingModeEnum::EXCLUSIVE,
                    roundingMode: TaxRoundingModeEnum::PER_COMPONENT,
                    calculationDate: $calculationDate,
                ),
            );

            $totalTaxable += $calculation->taxableAmount;
            $totalTax += $calculation->totalTax;
            $totalAmount += $calculation->totalAmount;

            $postingLines = [
                ...$postingLines,
                ...$postingService->buildLines(
                    $calculation,
                    TaxDirectionEnum::INPUT,
                    $calculationDate,
                ),
            ];
        }

        $aggregated = $aggregationService->aggregate(
            $postingLines,
        );

        $this->assertSame(78000.0, $totalTaxable);
        $this->assertSame(13200.0, $totalTax);
        $this->assertSame(91200.0, $totalAmount);

        $this->assertCount(2, $aggregated);

        $cgstLine = collect($aggregated)
            ->firstWhere('taxComponentId', $cgst->id);

        $sgstLine = collect($aggregated)
            ->firstWhere('taxComponentId', $sgst->id);

        $this->assertNotNull($cgstLine);
        $this->assertNotNull($sgstLine);

        $this->assertSame(6600.0, $cgstLine->amount);
        $this->assertSame(6600.0, $sgstLine->amount);

        $this->assertSame(
            $accountId,
            $cgstLine->accountId,
        );

        $this->assertSame(
            $accountId,
            $sgstLine->accountId,
        );

        $this->assertSame(
            TaxDirectionEnum::INPUT->value,
            $cgstLine->direction,
        );

        $this->assertSame(
            TaxDirectionEnum::INPUT->value,
            $sgstLine->direction,
        );

        $this->assertSame(
            13200.0,
            $cgstLine->amount + $sgstLine->amount,
        );
    }

    protected function taxGroup(string $code): TaxGroup
    {
        return TaxGroup::query()
            ->where('tenant_id', $this->tenant->id)
            ->where('code', $code)
            ->firstOrFail();
    }

    protected function makeComponent(
        TaxComponentTypeEnum $type,
    ): TaxComponent {
        return TaxComponent::query()
            ->where('tenant_id', $this->tenant->id)
            ->where('component_type', $type->value)
            ->firstOrFail();
    }

    protected function createMapping(
        TaxComponent $component,
        int $accountId,
        string $effectiveFrom,
    ): TaxAccountingMapping {
        return TaxAccountingMapping::query()->create([
            'tax_component_id' => $component->id,
            'account_id' => $accountId,
            'direction' => TaxDirectionEnum::INPUT->value,
            'is_active' => true,
            'effective_from' => $effectiveFrom,
            'effective_to' => null,
        ]);
    }

    protected function inputTaxAccountId(): int
    {
        return ChartOfAccount::query()
            ->where('tenant_id', $this->tenant->id)
            ->where(
                'account_code',
                AccountingAccounts::INPUT_TAX_RECEIVABLE,
            )
            ->valueOrFail('id');
    }
}