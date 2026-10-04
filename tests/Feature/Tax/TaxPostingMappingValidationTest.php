<?php

namespace Tests\Feature\Tax;

use App\Core\Exceptions\BusinessException;
use App\Modules\Accounting\Services\AccountingSetupService;
use App\Modules\Tax\Domain\TaxCalculationInput;
use App\Modules\Tax\Enums\TaxDirectionEnum;
use App\Modules\Tax\Enums\TaxPricingModeEnum;
use App\Modules\Tax\Enums\TaxRoundingModeEnum;
use App\Modules\Tax\Models\TaxGroup;
use App\Modules\Tax\Services\TaxCalculationService;
use App\Modules\Tax\Services\TaxPostingService;
use App\Modules\Tax\Services\TaxSetupService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreatesTenant;
use Tests\TestCase;

class TaxPostingMappingValidationTest extends TestCase
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

    public function test_it_rejects_tax_posting_when_accounting_mapping_is_missing(): void
    {
        $group = TaxGroup::query()
            ->where('tenant_id', $this->tenant->id)
            ->where('code', 'GST18-INTRA-IN')
            ->firstOrFail();

        $calculationDate = $group->effective_from->toDateString();

        $calculation = app(TaxCalculationService::class)->calculate(
            new TaxCalculationInput(
                taxGroupId: $group->id,
                taxableAmount: 50000,
                pricingMode: TaxPricingModeEnum::EXCLUSIVE,
                roundingMode: TaxRoundingModeEnum::PER_COMPONENT,
                calculationDate: $calculationDate,
            ),
        );

        $this->expectException(BusinessException::class);

        $this->expectExceptionMessage(
            'Tax accounting mapping not found for component.',
        );

        app(TaxPostingService::class)->buildLines(
            $calculation,
            TaxDirectionEnum::INPUT,
            $calculationDate,
        );
    }

    public function test_it_allows_zero_tax_without_accounting_mapping(): void
    {
        $group = TaxGroup::query()
            ->where('tenant_id', $this->tenant->id)
            ->where('code', 'GST0-INTRA-IN')
            ->firstOrFail();

        $calculationDate = $group->effective_from->toDateString();

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

        $this->assertSame([], $lines);
    }
}