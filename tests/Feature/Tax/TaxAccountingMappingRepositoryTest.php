<?php

namespace Tests\Feature\Tax;

use Tests\TestCase;

use Illuminate\Foundation\Testing\RefreshDatabase;

use Tests\Support\CreatesTenant;
use App\Modules\Accounting\Enums\AccountingAccounts;
use App\Modules\Accounting\Services\AccountingSetupService;

use App\Modules\Tax\Enums\TaxComponentTypeEnum;
use App\Modules\Tax\Enums\TaxDirectionEnum;
use App\Modules\Tax\Models\TaxAccountingMapping;
use App\Modules\Tax\Models\TaxComponent;

use App\Modules\Tax\Services\TaxSetupService;
use App\Modules\Tax\Repositories\Contracts\TaxAccountingMappingRepositoryInterface;
use App\Modules\Tenant\Models\Tenant;

class TaxAccountingMappingRepositoryTest extends TestCase
{
    use RefreshDatabase;
    use CreatesTenant;

    protected Tenant $tenant;

    protected TaxAccountingMappingRepositoryInterface $repository;

    protected TaxComponent $component;

    protected int $accountId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = $this->createTestTenant();

        app(AccountingSetupService::class)->setup($this->tenant);
        app(TaxSetupService::class)->setup($this->tenant);

        $this->repository = app(
            TaxAccountingMappingRepositoryInterface::class
        );

        $this->component = TaxComponent::query()
            ->where('tenant_id', $this->tenant->id)
            ->where(
                'component_type',
                TaxComponentTypeEnum::CGST->value
            )
            ->firstOrFail();

        $this->accountId = $this->getCashAccountId();
    }

    public function test_finds_active_mapping_effective_on_calculation_date(): void
    {
        $mapping = $this->createMapping(
            effectiveFrom: '2026-01-01',
            effectiveTo: '2026-12-31',
        );

        $result = $this->repository->findForPosting(
            $this->component->id,
            TaxDirectionEnum::INPUT->value,
            '2026-06-15',
        );

        $this->assertNotNull($result);
        $this->assertSame($mapping->id, $result->id);
        $this->assertSame(
            $this->accountId,
            $result->account_id
        );
        $this->assertNotNull($result->account);
    }

    public function test_returns_null_before_effective_date(): void
    {
        $this->createMapping(
            effectiveFrom: '2026-06-01',
            effectiveTo: '2026-12-31',
        );

        $result = $this->repository->findForPosting(
            $this->component->id,
            TaxDirectionEnum::INPUT->value,
            '2026-05-31',
        );

        $this->assertNull($result);
    }

    public function test_returns_null_after_effective_date(): void
    {
        $this->createMapping(
            effectiveFrom: '2026-01-01',
            effectiveTo: '2026-06-30',
        );

        $result = $this->repository->findForPosting(
            $this->component->id,
            TaxDirectionEnum::INPUT->value,
            '2026-07-01',
        );

        $this->assertNull($result);
    }

    public function test_finds_mapping_with_open_ended_effective_period(): void
    {
        $mapping = $this->createMapping(
            effectiveFrom: '2026-01-01',
            effectiveTo: null,
        );

        $result = $this->repository->findForPosting(
            $this->component->id,
            TaxDirectionEnum::INPUT->value,
            '2030-01-01',
        );

        $this->assertNotNull($result);
        $this->assertSame($mapping->id, $result->id);
    }

    public function test_ignores_inactive_mapping(): void
    {
        $this->createMapping(
            effectiveFrom: '2026-01-01',
            effectiveTo: '2026-12-31',
            isActive: false,
        );

        $result = $this->repository->findForPosting(
            $this->component->id,
            TaxDirectionEnum::INPUT->value,
            '2026-06-15',
        );

        $this->assertNull($result);
    }

    public function test_direction_is_part_of_mapping_lookup(): void
    {
        $mapping = $this->createMapping(
            effectiveFrom: '2026-01-01',
            effectiveTo: '2026-12-31',
            direction: TaxDirectionEnum::OUTPUT->value,
        );

        $inputResult = $this->repository->findForPosting(
            $this->component->id,
            TaxDirectionEnum::INPUT->value,
            '2026-06-15',
        );

        $outputResult = $this->repository->findForPosting(
            $this->component->id,
            TaxDirectionEnum::OUTPUT->value,
            '2026-06-15',
        );

        $this->assertNull($inputResult);
        $this->assertNotNull($outputResult);
        $this->assertSame($mapping->id, $outputResult->id);
    }

    protected function createMapping(
        string $effectiveFrom,
        ?string $effectiveTo,
        string $direction = 'INPUT',
        bool $isActive = true,
    ): TaxAccountingMapping {
        return TaxAccountingMapping::query()->create([
            'tax_component_id' => $this->component->id,
            'account_id' => $this->accountId,
            'direction' => $direction,
            'is_active' => $isActive,
            'effective_from' => $effectiveFrom,
            'effective_to' => $effectiveTo,
        ]);
    }

    protected function getCashAccountId(): int
    {
        return \App\Modules\Accounting\Models\ChartOfAccount::query()
            ->where('tenant_id', $this->tenant->id)
            ->where(
                'account_code',
                AccountingAccounts::CASH
            )
            ->valueOrFail('id');
    }
}