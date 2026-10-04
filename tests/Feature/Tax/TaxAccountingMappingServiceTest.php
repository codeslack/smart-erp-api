<?php

namespace Tests\Feature\Tax;

use Tests\TestCase;

use Laravel\Sanctum\Sanctum;
use Illuminate\Foundation\Testing\RefreshDatabase;

use App\Core\Exceptions\BusinessException;

use Tests\Support\CreatesUser;
use Tests\Support\CreatesTenant;

use App\Modules\User\Models\User;
use App\Modules\Tenant\Models\Tenant;


use App\Modules\Accounting\Enums\AccountingAccounts;
use App\Modules\Accounting\Models\ChartOfAccount;
use App\Modules\Accounting\Services\AccountingSetupService;

use App\Modules\Tax\Models\TaxComponent;
use App\Modules\Tax\Models\TaxAccountingMapping;

use App\Modules\Tax\Enums\TaxDirectionEnum;
use App\Modules\Tax\Enums\TaxComponentTypeEnum;

use App\Modules\Tax\Services\TaxSetupService;
use App\Modules\Tax\Services\TaxAccountingMappingService;


class TaxAccountingMappingServiceTest extends TestCase
{
    use RefreshDatabase;
    use CreatesTenant;
    use CreatesUser;

    protected Tenant $tenant;
    protected User $user;

    protected TaxAccountingMappingService $service;

    protected ChartOfAccount $cashAccount;

    protected TaxComponent $cgstComponent;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = $this->createTestTenant();

        app(AccountingSetupService::class)
            ->setup($this->tenant);

        app(TaxSetupService::class)
            ->setup($this->tenant);

        $this->cashAccount = ChartOfAccount::query()
            ->where('tenant_id', $this->tenant->id)
            ->where(
                'account_code',
                AccountingAccounts::CASH
            )
            ->firstOrFail();

        $this->cgstComponent = TaxComponent::query()
            ->where('tenant_id', $this->tenant->id)
            ->where('component_type', TaxComponentTypeEnum::CGST->value)
            ->firstOrFail();

        $this->user = $this->createTestUser();

        Sanctum::actingAs($this->user);

        $this->service = app(
            TaxAccountingMappingService::class
        );
    }

    public function test_mapping_can_be_created(): void
    {
        $mapping = $this->service->create([
            'tax_component_id' => $this->cgstComponent->id,
            'account_id' => $this->cashAccount->id,
            'direction' => TaxDirectionEnum::INPUT->value,
            'is_active' => true,
            'effective_from' => '2026-01-01',
            'effective_to' => '2026-12-31',
        ]);

        $this->assertInstanceOf(
            TaxAccountingMapping::class,
            $mapping
        );

        $this->assertSame(
            $this->cgstComponent->id,
            $mapping->tax_component_id
        );

        $this->assertSame(
            $this->cashAccount->id,
            $mapping->account_id
        );

        $this->assertSame(
            TaxDirectionEnum::INPUT->value,
            $mapping->direction
        );

        $this->assertTrue($mapping->is_active);
    }

    public function test_invalid_direction_is_rejected(): void
    {
        $this->expectException(BusinessException::class);

        $this->expectExceptionMessage(
            'Invalid tax accounting mapping direction.'
        );

        $this->service->create([
            'tax_component_id' => $this->cgstComponent->id,
            'account_id' => $this->cashAccount->id,
            'direction' => 'INVALID',
            'effective_from' => '2026-01-01',
            'effective_to' => null,
        ]);
    }

    public function test_invalid_effective_period_is_rejected(): void
    {
        $this->expectException(BusinessException::class);

        $this->expectExceptionMessage(
            'Tax accounting mapping effective end date cannot be before the start date.'
        );

        $this->service->create([
            'tax_component_id' => $this->cgstComponent->id,
            'account_id' => $this->cashAccount->id,
            'direction' => TaxDirectionEnum::INPUT->value,
            'effective_from' => '2026-12-31',
            'effective_to' => '2026-01-01',
        ]);
    }

    public function test_overlapping_period_is_rejected(): void
    {
        $this->service->create([
            'tax_component_id' => $this->cgstComponent->id,
            'account_id' => $this->cashAccount->id,
            'direction' => TaxDirectionEnum::INPUT->value,
            'effective_from' => '2026-01-01',
            'effective_to' => '2026-12-31',
        ]);

        $this->expectException(BusinessException::class);

        $this->expectExceptionMessage(
            'An overlapping tax accounting mapping period already exists.'
        );

        $this->service->create([
            'tax_component_id' => $this->cgstComponent->id,
            'account_id' => $this->cashAccount->id,
            'direction' => TaxDirectionEnum::INPUT->value,
            'effective_from' => '2026-06-01',
            'effective_to' => '2027-05-31',
        ]);
    }

    public function test_non_overlapping_period_is_allowed(): void
    {
        $first = $this->service->create([
            'tax_component_id' => $this->cgstComponent->id,
            'account_id' => $this->cashAccount->id,
            'direction' => TaxDirectionEnum::INPUT->value,
            'effective_from' => '2026-01-01',
            'effective_to' => '2026-12-31',
        ]);

        $second = $this->service->create([
            'tax_component_id' => $this->cgstComponent->id,
            'account_id' => $this->cashAccount->id,
            'direction' => TaxDirectionEnum::INPUT->value,
            'effective_from' => '2027-01-01',
            'effective_to' => null,
        ]);

        $this->assertNotSame(
            $first->id,
            $second->id
        );

        $this->assertSame(
            '2027-01-01',
            $second->effective_from->toDateString()
        );
    }

    public function test_same_component_with_different_direction_is_allowed(): void
    {
        $input = $this->service->create([
            'tax_component_id' => $this->cgstComponent->id,
            'account_id' => $this->cashAccount->id,
            'direction' => TaxDirectionEnum::INPUT->value,
            'effective_from' => '2026-01-01',
            'effective_to' => null,
        ]);

        $output = $this->service->create([
            'tax_component_id' => $this->cgstComponent->id,
            'account_id' => $this->cashAccount->id,
            'direction' => TaxDirectionEnum::OUTPUT->value,
            'effective_from' => '2026-01-01',
            'effective_to' => null,
        ]);

        $this->assertNotSame(
            $input->id,
            $output->id
        );

        $this->assertSame(
            TaxDirectionEnum::INPUT->value,
            $input->direction
        );

        $this->assertSame(
            TaxDirectionEnum::OUTPUT->value,
            $output->direction
        );
    }

    public function test_update_ignores_current_mapping_when_checking_overlap(): void
    {
        $mapping = $this->service->create([
            'tax_component_id' => $this->cgstComponent->id,
            'account_id' => $this->cashAccount->id,
            'direction' => TaxDirectionEnum::INPUT->value,
            'effective_from' => '2026-01-01',
            'effective_to' => '2026-12-31',
        ]);

        $updated = $this->service->update(
            $mapping,
            [
                'effective_from' => '2026-02-01',
                'effective_to' => '2026-11-30',
            ]
        );

        $this->assertSame(
            $mapping->id,
            $updated->id
        );

        $this->assertSame(
            '2026-02-01',
            $updated->effective_from->toDateString()
        );

        $this->assertSame(
            '2026-11-30',
            $updated->effective_to->toDateString()
        );
    }

    public function test_mapping_can_be_deleted(): void
    {
        $mapping = $this->service->create([
            'tax_component_id' => $this->cgstComponent->id,
            'account_id' => $this->cashAccount->id,
            'direction' => TaxDirectionEnum::INPUT->value,
            'effective_from' => '2026-01-01',
            'effective_to' => null,
        ]);

        $result = $this->service->delete($mapping);

        $this->assertTrue($result);

        $this->assertSoftDeleted(
            'tax_accounting_mappings',
            [
                'id' => $mapping->id,
            ]
        );
    }
}