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
use App\Modules\Tenant\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreatesTenant;
use Tests\TestCase;

class TaxPostingIntegrationTest extends TestCase
{
    use RefreshDatabase;
    use CreatesTenant;

    protected Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = $this->createTestTenant();

        app(AccountingSetupService::class)
            ->setup($this->tenant);

        app(TaxSetupService::class)
            ->setup($this->tenant);
    }

    public function test_it_calculates_and_builds_posting_lines_for_multiple_tax_components(): void
    {
        $group = $this->taxGroup('GST18-INTRA-IN');

        $calculationDate = $group->effective_from->toDateString();

        $cgst = $this->makeComponent(
            TaxComponentTypeEnum::CGST,
        );

        $sgst = $this->makeComponent(
            TaxComponentTypeEnum::SGST,
        );

        $accountId = $this->cashAccountId();

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
                taxableAmount: 50000,
                pricingMode: TaxPricingModeEnum::EXCLUSIVE,
                roundingMode: TaxRoundingModeEnum::PER_COMPONENT,
                calculationDate: $calculationDate,
            ),
        );

        $lines = app(TaxPostingService::class)->buildLines(
            $calculation,
            TaxDirectionEnum::INPUT,
            $calculationDate,
        );

        $this->assertCount(2, $lines);

        $this->assertSame(
            $cgst->id,
            $lines[0]->taxComponentId,
        );

        $this->assertSame(
            $sgst->id,
            $lines[1]->taxComponentId,
        );

        $this->assertSame(
            $accountId,
            $lines[0]->accountId,
        );

        $this->assertSame(
            $accountId,
            $lines[1]->accountId,
        );

        $this->assertSame(4500.0, $lines[0]->amount);
        $this->assertSame(4500.0, $lines[1]->amount);

        $this->assertSame(
            TaxDirectionEnum::INPUT->value,
            $lines[0]->direction,
        );

        $this->assertSame(
            TaxDirectionEnum::INPUT->value,
            $lines[1]->direction,
        );
    }

    public function test_it_aggregates_tax_postings_from_multiple_invoice_lines(): void
    {
        $group = $this->taxGroup('GST18-INTRA-IN');

        $calculationDate = $group->effective_from->toDateString();

        $cgst = $this->makeComponent(
            TaxComponentTypeEnum::CGST,
        );

        $sgst = $this->makeComponent(
            TaxComponentTypeEnum::SGST,
        );

        $accountId = $this->cashAccountId();

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
                taxableAmount: 20000,
                pricingMode: TaxPricingModeEnum::EXCLUSIVE,
                roundingMode: TaxRoundingModeEnum::PER_COMPONENT,
                calculationDate: $calculationDate,
            ),
        );

        $lines = [
            ...$postingService->buildLines(
                $firstLine,
                TaxDirectionEnum::INPUT,
                $calculationDate,
            ),
            ...$postingService->buildLines(
                $secondLine,
                TaxDirectionEnum::INPUT,
                $calculationDate,
            ),
        ];

        $aggregated = app(
            TaxPostingAggregationService::class,
        )->aggregate($lines);

        $this->assertCount(2, $aggregated);

        $this->assertSame(
            $cgst->id,
            $aggregated[0]->taxComponentId,
        );

        $this->assertSame(
            $sgst->id,
            $aggregated[1]->taxComponentId,
        );

        $this->assertSame(
            $accountId,
            $aggregated[0]->accountId,
        );

        $this->assertSame(
            $accountId,
            $aggregated[1]->accountId,
        );

        $this->assertSame(6300.0, $aggregated[0]->amount);
        $this->assertSame(6300.0, $aggregated[1]->amount);

        $this->assertSame(
            12600.0,
            array_sum(
                array_map(
                    fn ($line): float => $line->amount,
                    $aggregated,
                ),
            ),
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

    protected function cashAccountId(): int
    {
        return ChartOfAccount::query()
            ->where('tenant_id', $this->tenant->id)
            ->where('account_code', AccountingAccounts::CASH)
            ->valueOrFail('id');
    }
}