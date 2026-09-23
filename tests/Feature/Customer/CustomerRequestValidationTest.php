<?php

namespace Tests\Feature\Customer;

use Tests\TestCase;
use Tests\Support\CreatesTenant;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

use Laravel\Sanctum\Sanctum;

use App\Core\Tenant\TenantManager;
use App\Core\Enums\OpeningBalanceTypeEnum;

use App\Modules\Tenant\Models\Tenant;
use App\Modules\User\Models\User;
use App\Modules\Customer\Models\Customer;
use App\Modules\CustomerOpeningBill\Models\CustomerOpeningBill;
use App\Modules\Accounting\Services\AccountingSetupService;

class CustomerRequestValidationTest extends TestCase
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

    /*
    |--------------------------------------------------------------------------
    | Request Helpers
    |--------------------------------------------------------------------------
    */

    protected function postCustomer(array $data)
    {
        return $this->postJson(
            route('customers.store'),
            $data
        );
    }

    protected function updateCustomer(
        Customer $customer,
        array $data
    ) {
        return $this->putJson(
            route(
                'customers.update',
                $customer
            ),
            $data
        );
    }

    protected function createCustomer(): Customer
    {
        return Customer::query()->create([
            'name' => 'Test Customer ' . uniqid(),
            'code' => 'CUS-' . uniqid(),
            'is_active' => true,
        ]);
    }

    protected function validOpeningBill(): array
    {
        return [
            'bill_no' => 'INV-' . uniqid(),
            'bill_date' => '2026-01-01',
            'due_date' => '2026-01-31',
            'amount' => 1000,
            'balance_amount' => 1000,
            'balance_type' => OpeningBalanceTypeEnum::DEBIT->value,
            'notes' => 'Opening balance test',
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | Store Validation
    |--------------------------------------------------------------------------
    */

    public function test_store_rejects_missing_opening_bill_number(): void
    {
        $bill = $this->validOpeningBill();

        unset($bill['bill_no']);

        $response = $this->postCustomer([
            'name' => 'Validation Customer',
            'opening_bills' => [$bill],
        ]);

        $response->assertStatus(422);

        $response->assertJsonValidationErrors([
            'opening_bills.0.bill_no',
        ]);
    }

    public function test_store_rejects_missing_opening_bill_amount(): void
    {
        $bill = $this->validOpeningBill();

        unset($bill['amount']);

        $response = $this->postCustomer([
            'name' => 'Validation Customer',
            'opening_bills' => [$bill],
        ]);

        $response->assertStatus(422);

        $response->assertJsonValidationErrors([
            'opening_bills.0.amount',
        ]);
    }

    public function test_store_rejects_missing_opening_bill_balance_amount(): void
    {
        $bill = $this->validOpeningBill();

        unset($bill['balance_amount']);

        $response = $this->postCustomer([
            'name' => 'Validation Customer',
            'opening_bills' => [$bill],
        ]);

        $response->assertStatus(422);

        $response->assertJsonValidationErrors([
            'opening_bills.0.balance_amount',
        ]);
    }

    public function test_store_rejects_missing_opening_bill_balance_type(): void
    {
        $bill = $this->validOpeningBill();

        unset($bill['balance_type']);

        $response = $this->postCustomer([
            'name' => 'Validation Customer',
            'opening_bills' => [$bill],
        ]);

        $response->assertStatus(422);

        $response->assertJsonValidationErrors([
            'opening_bills.0.balance_type',
        ]);
    }

    public function test_store_rejects_invalid_opening_bill_balance_type(): void
    {
        $bill = $this->validOpeningBill();

        $bill['balance_type'] = 'invalid';

        $response = $this->postCustomer([
            'name' => 'Validation Customer',
            'opening_bills' => [$bill],
        ]);

        $response->assertStatus(422);

        $response->assertJsonValidationErrors([
            'opening_bills.0.balance_type',
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Update Validation
    |--------------------------------------------------------------------------
    */

    public function test_update_allows_existing_opening_bill(): void
    {
        $customer = $this->createCustomer();

        $bill = CustomerOpeningBill::query()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $customer->id,
            ...$this->validOpeningBill(),
        ]);

        $updatedBill = $this->validOpeningBill();

        $response = $this->updateCustomer(
            $customer,
            [
                'opening_bills' => [
                    [
                        'uuid' => $bill->uuid,
                        ...$updatedBill,
                    ],
                ],
            ]
        );

        // $response->dump();

        $response->assertSuccessful();

        $response->assertJsonMissingValidationErrors();
    }

    public function test_update_allows_adding_new_opening_bill(): void
    {
        $customer = $this->createCustomer();

        $response = $this->updateCustomer(
            $customer,
            [
                'opening_bills' => [
                    $this->validOpeningBill(),
                ],
            ]
        );

        $response->assertSuccessful();

        $response->assertJsonMissingValidationErrors();
    }

    public function test_update_allows_delete_opening_bill_with_uuid_only(): void
    {
        $customer = $this->createCustomer();

        $bill = CustomerOpeningBill::query()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $customer->id,
            ...$this->validOpeningBill(),
        ]);

        $response = $this->updateCustomer(
            $customer,
            [
                'opening_bills' => [
                    [
                        'uuid' => $bill->uuid,
                        'delete' => true,
                    ],
                ],
            ]
        );

        $response->assertSuccessful();

        $response->assertJsonMissingValidationErrors();
    }

    public function test_update_rejects_delete_without_uuid(): void
    {
        $customer = $this->createCustomer();

        $response = $this->updateCustomer(
            $customer,
            [
                'opening_bills' => [
                    [
                        'delete' => true,
                    ],
                ],
            ]
        );

        $response->assertStatus(422);

        $response->assertJsonValidationErrors([
            'opening_bills.0.uuid',
        ]);
    }

    public function test_update_rejects_invalid_opening_bill_uuid(): void
    {
        $customer = $this->createCustomer();

        $response = $this->updateCustomer(
            $customer,
            [
                'opening_bills' => [
                    [
                        'uuid' => 'not-a-uuid',
                        'delete' => true,
                    ],
                ],
            ]
        );

        $response->assertStatus(422);

        $response->assertJsonValidationErrors([
            'opening_bills.0.uuid',
        ]);
    }

    public function test_update_rejects_missing_bill_number_for_non_delete_bill(): void
    {
        $customer = $this->createCustomer();

        $bill = $this->validOpeningBill();

        unset($bill['bill_no']);

        $response = $this->updateCustomer(
            $customer,
            [
                'opening_bills' => [$bill],
            ]
        );

        $response->assertStatus(422);

        $response->assertJsonValidationErrors([
            'opening_bills.0.bill_no',
        ]);
    }

    public function test_update_rejects_missing_amount_for_non_delete_bill(): void
    {
        $customer = $this->createCustomer();

        $bill = $this->validOpeningBill();

        unset($bill['amount']);

        $response = $this->updateCustomer(
            $customer,
            [
                'opening_bills' => [$bill],
            ]
        );

        $response->assertStatus(422);

        $response->assertJsonValidationErrors([
            'opening_bills.0.amount',
        ]);
    }

    public function test_update_rejects_invalid_balance_type(): void
    {
        $customer = $this->createCustomer();

        $bill = $this->validOpeningBill();

        $bill['balance_type'] = 'invalid';

        $response = $this->updateCustomer(
            $customer,
            [
                'opening_bills' => [$bill],
            ]
        );

        $response->assertStatus(422);

        $response->assertJsonValidationErrors([
            'opening_bills.0.balance_type',
        ]);
    }
}
