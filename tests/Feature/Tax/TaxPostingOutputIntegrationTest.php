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

class TaxPostingOutputIntegrationTest extends TestCase
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

    public function test_it_builds_output_tax_postings_for_sales_invoice(): void
    {
        $group = $this->taxGroup('GST18-INTRA-OUT');

        $calculationDate = $group->effective_from->toDateString();

        $cgst = $this->makeComponent(
            TaxComponentTypeEnum::CGST,
        );

        $sgst = $this->makeComponent(
            TaxComponentTypeEnum::SGST,
        );

        $accountId = $this->outputTaxAccountId();

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

        $calculation = app(TaxCalculationService::class)->calculate(
            new TaxCalculationInput(
                taxGroupId: $group->id,
                taxableAmount: 100000,
                pricingMode: TaxPricingModeEnum::EXCLUSIVE,
                roundingMode: TaxRoundingModeEnum::PER_COMPONENT,
                calculationDate: $calculationDate,
            ),
        );

        $lines = app(TaxPostingService::class)->buildLines(
            $calculation,
            TaxDirectionEnum::OUTPUT,
            $calculationDate,
        );

        $this->assertCount(2, $lines);

        $cgstLine = collect($lines)
            ->firstWhere('taxComponentId', $cgst->id);

        $sgstLine = collect($lines)
            ->firstWhere('taxComponentId', $sgst->id);

        $this->assertNotNull($cgstLine);
        $this->assertNotNull($sgstLine);

        $this->assertSame(
            9000.0,
            $cgstLine->amount,
        );

        $this->assertSame(
            9000.0,
            $sgstLine->amount,
        );

        $this->assertSame(
            $accountId,
            $cgstLine->accountId,
        );

        $this->assertSame(
            $accountId,
            $sgstLine->accountId,
        );

        $this->assertSame(
            TaxDirectionEnum::OUTPUT->value,
            $cgstLine->direction,
        );

        $this->assertSame(
            TaxDirectionEnum::OUTPUT->value,
            $sgstLine->direction,
        );

        $this->assertSame(
            18000.0,
            $cgstLine->amount + $sgstLine->amount,
        );
    }

    public function test_it_aggregates_output_tax_from_multiple_sales_lines(): void
    {
        $group = $this->taxGroup('GST18-INTRA-OUT');

        $calculationDate = $group->effective_from->toDateString();

        $cgst = $this->makeComponent(
            TaxComponentTypeEnum::CGST,
        );

        $sgst = $this->makeComponent(
            TaxComponentTypeEnum::SGST,
        );

        $accountId = $this->outputTaxAccountId();

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

        $firstLine = $calculationService->calculate(
            new TaxCalculationInput(
                taxGroupId: $group->id,
                taxableAmount: 50000,
                pricingMode: TaxPricingModeEnum::EXCLUSIVE,
                roundingMode: TaxRoundingModeEnum::PER_COMPONENT,
                calculationDate: $calculationDate,
            ),
        );

        $secondLine = $calculationService->calculate(
            new TaxCalculationInput(
                taxGroupId: $group->id,
                taxableAmount: 25000,
                pricingMode: TaxPricingModeEnum::EXCLUSIVE,
                roundingMode: TaxRoundingModeEnum::PER_COMPONENT,
                calculationDate: $calculationDate,
            ),
        );

        $lines = [
            ...$postingService->buildLines(
                $firstLine,
                TaxDirectionEnum::OUTPUT,
                $calculationDate,
            ),
            ...$postingService->buildLines(
                $secondLine,
                TaxDirectionEnum::OUTPUT,
                $calculationDate,
            ),
        ];

        $aggregated = app(
            TaxPostingAggregationService::class,
        )->aggregate($lines);

        $this->assertCount(2, $aggregated);

        $cgstLine = collect($aggregated)
            ->firstWhere('taxComponentId', $cgst->id);

        $sgstLine = collect($aggregated)
            ->firstWhere('taxComponentId', $sgst->id);

        $this->assertNotNull($cgstLine);
        $this->assertNotNull($sgstLine);

        $this->assertSame(6750.0, $cgstLine->amount);
        $this->assertSame(6750.0, $sgstLine->amount);

        $this->assertSame(
            $accountId,
            $cgstLine->accountId,
        );

        $this->assertSame(
            $accountId,
            $sgstLine->accountId,
        );

        $this->assertSame(
            TaxDirectionEnum::OUTPUT->value,
            $cgstLine->direction,
        );

        $this->assertSame(
            TaxDirectionEnum::OUTPUT->value,
            $sgstLine->direction,
        );

        $this->assertSame(
            13500.0,
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
            'direction' => TaxDirectionEnum::OUTPUT->value,
            'is_active' => true,
            'effective_from' => $effectiveFrom,
            'effective_to' => null,
        ]);
    }

    protected function outputTaxAccountId(): int
    {
        return ChartOfAccount::query()
            ->where('tenant_id', $this->tenant->id)
            ->where(
                'account_code',
                AccountingAccounts::OUTPUT_TAX_PAYABLE,
            )
            ->valueOrFail('id');
    }
}