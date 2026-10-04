<?php

namespace Tests\Feature\Tax;

use Tests\TestCase;

use Laravel\Sanctum\Sanctum;
use Illuminate\Foundation\Testing\RefreshDatabase;

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
use App\Modules\Tax\Services\TaxSetupService;
use App\Modules\Tax\Enums\TaxComponentTypeEnum;


class TaxAccountingMappingApiTest extends TestCase
{
    use RefreshDatabase;
    use CreatesTenant;
    use CreatesUser;

    protected Tenant $tenant;

    protected User $user;

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
            ->where(
                'component_type',
                TaxComponentTypeEnum::CGST->value
            )
            ->firstOrFail();

        $this->user = $this->createTestUser();

        Sanctum::actingAs($this->user);
    }

    public function test_mapping_can_be_created_through_api(): void
    {
        $response = $this->postJson(
            '/api/tax-accounting-mappings',
            [
                'tax_component_id' => $this->cgstComponent->id,
                'account_id' => $this->cashAccount->id,
                'direction' => TaxDirectionEnum::INPUT->value,
                'is_active' => true,
                'effective_from' => '2026-01-01',
                'effective_to' => null,
            ]
        );

        $response
            ->assertSuccessful()
            ->assertJsonPath(
                'data.tax_component_id',
                $this->cgstComponent->id
            )
            ->assertJsonPath(
                'data.account_id',
                $this->cashAccount->id
            )
            ->assertJsonPath(
                'data.direction',
                TaxDirectionEnum::INPUT->value
            );

        $this->assertDatabaseHas(
            'tax_accounting_mappings',
            [
                'tenant_id' => $this->tenant->id,
                'tax_component_id' => $this->cgstComponent->id,
                'account_id' => $this->cashAccount->id,
                'direction' => TaxDirectionEnum::INPUT->value,
            ]
        );
    }

    public function test_mapping_can_be_viewed_through_api(): void
    {
        $mapping = TaxAccountingMapping::query()->create([
            'tax_component_id' => $this->cgstComponent->id,
            'account_id' => $this->cashAccount->id,
            'direction' => TaxDirectionEnum::INPUT->value,
            'is_active' => true,
            'effective_from' => '2026-01-01',
        ]);

        $response = $this->getJson(
            "/api/tax-accounting-mappings/{$mapping->uuid}"
        );

        $response
            ->assertSuccessful()
            ->assertJsonPath(
                'data.uuid',
                $mapping->uuid
            );
    }

    public function test_mapping_can_be_updated_through_api(): void
    {
        $mapping = TaxAccountingMapping::query()->create([
            'tax_component_id' => $this->cgstComponent->id,
            'account_id' => $this->cashAccount->id,
            'direction' => TaxDirectionEnum::INPUT->value,
            'is_active' => true,
            'effective_from' => '2026-01-01',
        ]);

        $response = $this->putJson(
            "/api/tax-accounting-mappings/{$mapping->uuid}",
            [
                'tax_component_id' => $this->cgstComponent->id,
                'account_id' => $this->cashAccount->id,
                'direction' => TaxDirectionEnum::OUTPUT->value,
                'is_active' => true,
                'effective_from' => '2026-01-01',
                'effective_to' => null,
            ]
        );

        $response
            ->assertSuccessful()
            ->assertJsonPath(
                'data.direction',
                TaxDirectionEnum::OUTPUT->value
            );

        $this->assertDatabaseHas(
            'tax_accounting_mappings',
            [
                'id' => $mapping->id,
                'direction' => TaxDirectionEnum::OUTPUT->value,
            ]
        );
    }

    public function test_mapping_can_be_deleted_through_api(): void
    {
        $mapping = TaxAccountingMapping::query()->create([
            'tax_component_id' => $this->cgstComponent->id,
            'account_id' => $this->cashAccount->id,
            'direction' => TaxDirectionEnum::INPUT->value,
            'is_active' => true,
            'effective_from' => '2026-01-01',
        ]);

        $response = $this->deleteJson(
            "/api/tax-accounting-mappings/{$mapping->uuid}"
        );

        $response->assertSuccessful();

        $this->assertSoftDeleted(
            'tax_accounting_mappings',
            [
                'id' => $mapping->id,
            ]
        );
    }

    public function test_invalid_direction_is_rejected(): void
    {
        $response = $this->postJson(
            '/api/tax-accounting-mappings',
            [
                'tax_component_id' => $this->cgstComponent->id,
                'account_id' => $this->cashAccount->id,
                'direction' => 'INVALID',
                'is_active' => true,
                'effective_from' => '2026-01-01',
            ]
        );

        $response->assertStatus(422);
    }

    public function test_invalid_tax_component_is_rejected(): void
    {
        $response = $this->postJson(
            '/api/tax-accounting-mappings',
            [
                'tax_component_id' => 999999,
                'account_id' => $this->cashAccount->id,
                'direction' => TaxDirectionEnum::INPUT->value,
                'is_active' => true,
                'effective_from' => '2026-01-01',
            ]
        );

        $response->assertStatus(422);
    }

    public function test_invalid_account_is_rejected(): void
    {
        $response = $this->postJson(
            '/api/tax-accounting-mappings',
            [
                'tax_component_id' => $this->cgstComponent->id,
                'account_id' => 999999,
                'direction' => TaxDirectionEnum::INPUT->value,
                'is_active' => true,
                'effective_from' => '2026-01-01',
            ]
        );

        $response->assertStatus(422);
    }

    public function test_invalid_effective_period_is_rejected(): void
    {
        $response = $this->postJson(
            '/api/tax-accounting-mappings',
            [
                'tax_component_id' => $this->cgstComponent->id,
                'account_id' => $this->cashAccount->id,
                'direction' => TaxDirectionEnum::INPUT->value,
                'is_active' => true,
                'effective_from' => '2026-12-31',
                'effective_to' => '2026-01-01',
            ]
        );

        $response->assertStatus(422);
    }

    public function test_non_existing_mapping_returns_not_found(): void
    {
        $response = $this->getJson(
            '/api/tax-accounting-mappings/non-existing-uuid'
        );

        $response
            ->assertStatus(404)
            ->assertJson([
                'success' => false,
                'message' => 'Tax accounting mapping not found.',
                'errors' => null,
            ]);
    }
}