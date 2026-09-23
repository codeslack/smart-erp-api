<?php

namespace Tests\Feature\Customer;

use Tests\TestCase;
use Tests\Support\CreatesTenant;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

use Laravel\Sanctum\Sanctum;

use App\Core\Enums\OpeningBalanceTypeEnum;
use App\Core\Tenant\TenantManager;

use App\Modules\Tenant\Models\Tenant;
use App\Modules\User\Models\User;
use App\Modules\Customer\Models\Customer;
use App\Modules\CustomerOpeningBill\Models\CustomerOpeningBill;
use App\Modules\Accounting\Services\AccountingSetupService;

class CustomerResourceTest extends TestCase
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

    protected function createCustomer(): Customer
    {
        return Customer::query()->create([
            'name' => 'Test Customer ' . uniqid(),
            'code' => 'CUS-' . uniqid(),
            'is_active' => true,
        ]);
    }

    protected function createOpeningBill(
        Customer $customer,
        array $overrides = []
    ): CustomerOpeningBill {
        return CustomerOpeningBill::query()->create(array_merge([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $customer->id,
            'bill_no' => 'INV-' . uniqid(),
            'bill_date' => '2026-01-01',
            'due_date' => '2026-01-31',
            'amount' => 1000,
            'balance_amount' => 750,
            'balance_type' => OpeningBalanceTypeEnum::DEBIT->value,
            'notes' => 'Opening balance test',
        ], $overrides));
    }

    public function test_show_returns_customer_with_opening_bills(): void
    {
        $customer = $this->createCustomer();

        $bill = $this->createOpeningBill($customer);

        $response = $this->getJson(
            route('customers.show', $customer)
        );

        $response->assertSuccessful();

        $response->assertJsonPath(
            'data.uuid',
            $customer->uuid
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
            OpeningBalanceTypeEnum::DEBIT->value
        );

        $response->assertJsonPath(
            'data.opening_bills.0.paid_amount',
            250
        );

        $response->assertJsonPath(
            'data.opening_bills.0.is_debit',
            true
        );

        $response->assertJsonPath(
            'data.opening_bills.0.is_credit',
            false
        );
    }

    public function test_show_returns_empty_opening_bills_when_customer_has_none(): void
    {
        $customer = $this->createCustomer();

        $response = $this->getJson(
            route('customers.show', $customer)
        );

        $response->assertSuccessful();

        $response->assertJsonPath(
            'data.uuid',
            $customer->uuid
        );

        $response->assertJsonPath(
            'data.opening_bills',
            []
        );
    }

    public function test_index_returns_customer_opening_bills(): void
    {
        $customer = $this->createCustomer();

        $bill = $this->createOpeningBill($customer);

        $response = $this->getJson(
            route('customers.index')
        );

        $response->assertSuccessful();

        $response->assertJsonPath(
            'data.0.uuid',
            $customer->uuid
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

    public function test_customer_opening_bills_are_tenant_scoped(): void
    {
        $customer = $this->createCustomer();

        $this->createOpeningBill($customer);

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
            route('customers.index')
        );

        $response->assertSuccessful();

        $response->assertJsonPath(
            'data',
            []
        );
    }
}