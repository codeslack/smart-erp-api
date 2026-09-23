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

class CustomerOpeningBalanceApiTest extends TestCase
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

    protected function validOpeningBill(
        array $overrides = []
    ): array {
        return array_merge([
            'bill_no' => 'INV-' . uniqid(),
            'bill_date' => '2026-01-01',
            'due_date' => '2026-01-31',
            'amount' => 1000,
            'balance_amount' => 1000,
            'balance_type' => OpeningBalanceTypeEnum::DEBIT->value,
            'notes' => 'Opening balance test',
        ], $overrides);
    }

    protected function createCustomer(): Customer
    {
        return Customer::query()->create([
            'name' => 'Test Customer ' . uniqid(),
            'code' => 'CUS-' . uniqid(),
            'is_active' => true,
        ]);
    }

    public function test_create_customer_with_opening_bill(): void
    {
        $bill = $this->validOpeningBill();

        $response = $this->postJson(
            route('customers.store'),
            [
                'name' => 'Opening Balance Customer',
                'code' => 'CUS-' . uniqid(),
                'is_active' => true,
                'opening_bills' => [$bill],
            ]
        );

        $response->assertCreated();

        $customer = Customer::query()
            ->where('name', 'Opening Balance Customer')
            ->firstOrFail();

        $openingBill = CustomerOpeningBill::query()
            ->where('customer_id', $customer->id)
            ->first();

        $this->assertNotNull($openingBill);

        $this->assertSame(
            $bill['bill_no'],
            $openingBill->bill_no
        );

        $this->assertEquals(
            $bill['amount'],
            $openingBill->amount
        );

        $this->assertEquals(
            $bill['balance_amount'],
            $openingBill->balance_amount
        );

        $this->assertSame(
            $bill['balance_type'],
            $openingBill->balance_type->value
        );

        $response->assertJsonPath(
            'data.uuid',
            $customer->uuid
        );

        $response->assertJsonPath(
            'data.opening_bills.0.uuid',
            $openingBill->uuid
        );
    }

    public function test_update_customer_adds_opening_bill(): void
    {
        $customer = $this->createCustomer();

        $bill = $this->validOpeningBill([
            'bill_no' => 'INV-ADD-' . uniqid(),
        ]);

        $response = $this->putJson(
            route('customers.update', $customer),
            [
                'opening_bills' => [$bill],
            ]
        );

        $response->assertSuccessful();

        $openingBill = CustomerOpeningBill::query()
            ->where('customer_id', $customer->id)
            ->first();

        $this->assertNotNull($openingBill);

        $this->assertSame(
            $bill['bill_no'],
            $openingBill->bill_no
        );

        $response->assertJsonPath(
            'data.opening_bills.0.uuid',
            $openingBill->uuid
        );
    }

    public function test_update_customer_updates_existing_opening_bill(): void
    {
        $customer = $this->createCustomer();

        $openingBill = CustomerOpeningBill::query()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $customer->id,
            ...$this->validOpeningBill([
                'amount' => 2000,
                'balance_amount' => 2000,
            ]),
        ]);

        $response = $this->putJson(
            route('customers.update', $customer),
            [
                'opening_bills' => [
                    [
                        'uuid' => $openingBill->uuid,
                        'bill_no' => 'INV-UPDATED',
                        'bill_date' => '2026-02-01',
                        'due_date' => '2026-03-01',
                        'amount' => 3000,
                        'balance_amount' => 2500,
                        'balance_type' => OpeningBalanceTypeEnum::DEBIT->value,
                        'notes' => 'Updated opening bill',
                    ],
                ],
            ]
        );

        $response->assertSuccessful();

        $openingBill->refresh();

        $this->assertSame(
            'INV-UPDATED',
            $openingBill->bill_no
        );

        $this->assertEquals(
            3000,
            $openingBill->amount
        );

        $this->assertEquals(
            2500,
            $openingBill->balance_amount
        );

        $this->assertSame(
            'Updated opening bill',
            $openingBill->notes
        );

        $response->assertJsonPath(
            'data.opening_bills.0.uuid',
            $openingBill->uuid
        );
    }

    public function test_update_customer_deletes_opening_bill(): void
    {
        $customer = $this->createCustomer();

        $openingBill = CustomerOpeningBill::query()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $customer->id,
            ...$this->validOpeningBill(),
        ]);

        $response = $this->putJson(
            route('customers.update', $customer),
            [
                'opening_bills' => [
                    [
                        'uuid' => $openingBill->uuid,
                        'delete' => true,
                    ],
                ],
            ]
        );

        $response->assertSuccessful();

        $this->assertSoftDeleted(
            'customer_opening_bills',
            [
                'id' => $openingBill->id,
            ]
        );

        $response->assertJsonPath(
            'data.opening_bills',
            []
        );
    }

    public function test_update_customer_without_opening_bills_leaves_existing_bill_unchanged(): void
    {
        $customer = $this->createCustomer();

        $openingBill = CustomerOpeningBill::query()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $customer->id,
            ...$this->validOpeningBill([
                'amount' => 5000,
                'balance_amount' => 4000,
            ]),
        ]);

        $response = $this->putJson(
            route('customers.update', $customer),
            [
                'name' => 'Updated Customer Name',
            ]
        );

        $response->assertSuccessful();

        $openingBill->refresh();

        $this->assertSame(
            'Updated Customer Name',
            $customer->refresh()->name
        );

        $this->assertEquals(
            5000,
            $openingBill->amount
        );

        $this->assertEquals(
            4000,
            $openingBill->balance_amount
        );
    }

    public function test_update_customer_rejects_opening_bill_from_another_customer(): void
    {
        $customer = $this->createCustomer();
        $otherCustomer = $this->createCustomer();

        $openingBill = CustomerOpeningBill::query()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $otherCustomer->id,
            ...$this->validOpeningBill(),
        ]);

        $response = $this->putJson(
            route('customers.update', $customer),
            [
                'opening_bills' => [
                    [
                        'uuid' => $openingBill->uuid,
                        'delete' => true,
                    ],
                ],
            ]
        );

        $response->assertStatus(422);

        $response->assertJson([
            'success' => false,
        ]);

        $openingBill->refresh();

        $this->assertNull(
            $openingBill->deleted_at
        );
    }
}