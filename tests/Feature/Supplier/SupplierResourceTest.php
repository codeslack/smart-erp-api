<?php

namespace Tests\Feature\Supplier;

use Tests\TestCase;
use Tests\Support\CreatesTenant;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

use Laravel\Sanctum\Sanctum;

use App\Core\Enums\OpeningBalanceTypeEnum;
use App\Core\Tenant\TenantManager;

use App\Modules\Tenant\Models\Tenant;
use App\Modules\User\Models\User;
use App\Modules\Supplier\Models\Supplier;
use App\Modules\SupplierOpeningBill\Models\SupplierOpeningBill;
use App\Modules\Accounting\Services\AccountingSetupService;

class SupplierResourceTest extends TestCase
{
    use RefreshDatabase;
    use CreatesTenant;

    protected Tenant $tenant;
    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = $this->createTestTenant();

        app(TenantManager::class)
            ->setTenant($this->tenant);

        app(AccountingSetupService::class)
            ->setup($this->tenant);

        $this->user = User::query()->create([
            'uuid' => (string) Str::uuid(),
            'code' => 'TEST-USER',
            'tenant_id' => $this->tenant->id,
            'name' => 'Test User',
            'email' => 'test-' . uniqid() . '@example.com',
            'password' => 'password',
            'is_active' => true,
        ]);

        Sanctum::actingAs($this->user);
    }

    protected function createSupplier(): Supplier
    {
        return Supplier::query()->create([
            'name' => 'Test Supplier ' . uniqid(),
            'code' => 'SUP-' . uniqid(),
            'is_active' => true,
        ]);
    }

    protected function createOpeningBill(
        Supplier $supplier,
        array $overrides = []
    ): SupplierOpeningBill {
        return SupplierOpeningBill::query()->create(array_merge([
            'tenant_id' => $this->tenant->id,
            'supplier_id' => $supplier->id,
            'bill_no' => 'BILL-' . uniqid(),
            'bill_date' => '2026-01-01',
            'due_date' => '2026-01-31',
            'amount' => 1000,
            'balance_amount' => 750,
            'balance_type' => OpeningBalanceTypeEnum::CREDIT->value,
            'notes' => 'Opening balance test',
        ], $overrides));
    }

    public function test_show_returns_supplier_with_opening_bills(): void
    {
        $supplier = $this->createSupplier();

        $bill = $this->createOpeningBill($supplier);

        $response = $this->getJson(
            route('suppliers.show', $supplier)
        );

        $response->assertSuccessful();

        $response->assertJsonPath(
            'data.uuid',
            $supplier->uuid
        );

        $response->assertJsonPath(
            'data.opening_bills.0.uuid',
            $bill->uuid
        );

        $response->assertJsonPath(
            'data.opening_bills.0.bill_no',
            $bill->bill_no
        );

        $response->assertJsonPath(
            'data.opening_bills.0.amount',
            '1000.0000'
        );

        $response->assertJsonPath(
            'data.opening_bills.0.balance_amount',
            '750.0000'
        );

        $response->assertJsonPath(
            'data.opening_bills.0.balance_type',
            OpeningBalanceTypeEnum::CREDIT->value
        );

        $response->assertJsonPath(
            'data.opening_bills.0.paid_amount',
            250
        );

        $response->assertJsonPath(
            'data.opening_bills.0.is_debit',
            false
        );

        $response->assertJsonPath(
            'data.opening_bills.0.is_credit',
            true
        );
    }

    public function test_show_returns_empty_opening_bills_when_supplier_has_none(): void
    {
        $supplier = $this->createSupplier();

        $response = $this->getJson(
            route('suppliers.show', $supplier)
        );

        $response->assertSuccessful();

        $response->assertJsonPath(
            'data.uuid',
            $supplier->uuid
        );

        $response->assertJsonPath(
            'data.opening_bills',
            []
        );
    }

    public function test_index_returns_supplier_opening_bills(): void
    {
        $supplier = $this->createSupplier();

        $bill = $this->createOpeningBill($supplier);

        $response = $this->getJson(
            route('suppliers.index')
        );

        $response->assertSuccessful();

        $response->assertJsonPath(
            'data.0.uuid',
            $supplier->uuid
        );

        $response->assertJsonPath(
            'data.0.opening_bills.0.uuid',
            $bill->uuid
        );

        $response->assertJsonPath(
            'data.0.opening_bills.0.bill_no',
            $bill->bill_no
        );
    }

    public function test_supplier_opening_bills_are_tenant_scoped(): void
    {
        $supplier = $this->createSupplier();

        $this->createOpeningBill($supplier);

        $otherTenant = $this->createTestTenant();

        app(TenantManager::class)
            ->setTenant($otherTenant);

        $otherUser = User::query()->create([
            'uuid' => (string) Str::uuid(),
            'code' => 'OTHER-USER',
            'tenant_id' => $otherTenant->id,
            'name' => 'Other User',
            'email' => 'other-' . uniqid() . '@example.com',
            'password' => 'password',
            'is_active' => true,
        ]);

        Sanctum::actingAs($otherUser);

        $response = $this->getJson(
            route('suppliers.index')
        );

        $response->assertSuccessful();

        $response->assertJsonPath(
            'data',
            []
        );
    }
}