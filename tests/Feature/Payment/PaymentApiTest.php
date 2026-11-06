<?php

namespace Tests\Feature\Payment;

use Tests\TestCase;
use Tests\Support\CreatesTenant;
use Tests\Support\CreatesUser;

use Illuminate\Foundation\Testing\RefreshDatabase;

use Laravel\Sanctum\Sanctum;

use App\Core\Enums\DocumentStatusEnum;
use App\Core\Tenant\TenantManager;

use App\Modules\Tenant\Models\Tenant;
use App\Modules\User\Models\User;
use App\Modules\Customer\Models\Customer;
use App\Modules\Payment\Models\Payment;
use App\Modules\Payment\Models\PaymentAllocation;
use App\Modules\Payment\Enums\PaymentMethodEnum;
use App\Modules\Payment\Enums\PaymentPartyTypeEnum;
use App\Modules\Payment\Enums\PaymentPurposeEnum;
use App\Modules\Payment\Enums\PaymentTypeEnum;
use App\Modules\Accounting\Enums\AccountingAccounts;
use App\Modules\Accounting\Models\ChartOfAccount;
use App\Modules\Accounting\Services\AccountingSetupService;

class PaymentApiTest extends TestCase
{
    use RefreshDatabase;
    use CreatesTenant;
    use CreatesUser;

    protected Tenant $tenant;
    protected User $user;
    protected ChartOfAccount $cashAccount;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = $this->createTestTenant();

        app(TenantManager::class)
            ->setTenant($this->tenant);

        app(AccountingSetupService::class)
            ->setup($this->tenant);

        $this->cashAccount = ChartOfAccount::query()
            ->where('tenant_id', $this->tenant->id)
            ->where(
                'account_code',
                AccountingAccounts::CASH
            )
            ->firstOrFail();

        $this->user = $this->createTestUser();

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

    protected function validPayment(
        Customer $customer,
        array $overrides = []
    ): array {
        return array_merge([
            'payment_no' => 'REC-' . uniqid(),
            'payment_type' => PaymentTypeEnum::RECEIPT->value,
            'purpose' => PaymentPurposeEnum::CUSTOMER_PAYMENT->value,
            'payment_date' => '2026-09-28',
            'party_type' => PaymentPartyTypeEnum::CUSTOMER->value,
            'party_id' => $customer->id,
            'account_id' => $this->cashAccount->id,
            'amount' => 10000,
            'payment_method' => PaymentMethodEnum::CASH->value,
            'reference_no' => null,
            'notes' => 'Payment API test',
            'status' => DocumentStatusEnum::DRAFT->value,
        ], $overrides);
    }

    public function test_create_payment(): void
    {
        $customer = $this->createCustomer();

        $data = $this->validPayment($customer);

        unset($data['status']);

        $response = $this->postJson(
            route('payments.store'),
            $data
        );

        $response->assertCreated();

        app(\App\Core\Tenant\TenantManager::class)
            ->setTenant($this->tenant);

        $payment = Payment::query()
            ->where(
                'payment_no',
                $data['payment_no']
            )
            ->firstOrFail();

        $this->assertSame(
            $this->tenant->id,
            $payment->tenant_id
        );

        $this->assertSame(
            $data['party_id'],
            $payment->party_id
        );

        $this->assertEquals(
            $data['amount'],
            $payment->amount
        );

        $this->assertSame(
            DocumentStatusEnum::DRAFT->value,
            $payment->status
        );

        $response->assertJsonPath(
            'data.uuid',
            $payment->uuid
        );

        $response->assertJsonPath(
            'data.payment_no',
            $data['payment_no']
        );
    }

    public function test_payment_can_be_listed(): void
    {
        $customer = $this->createCustomer();

        Payment::query()->create(
            $this->validPayment($customer)
        );

        $response = $this->getJson(
            route('payments.index')
        );

        $response->assertSuccessful();

        $response->assertJsonStructure([
            'success',
            'message',
            'data',
            'links',
            'meta',
        ]);

        $response->assertJsonCount(
            1,
            'data'
        );
    }

    public function test_payment_can_be_shown(): void
    {
        $customer = $this->createCustomer();

        $payment = Payment::query()->create(
            $this->validPayment($customer)
        );

        $response = $this->getJson(
            route(
                'payments.show',
                $payment
            )
        );

        $response->assertSuccessful();

        $response->assertJsonPath(
            'data.uuid',
            $payment->uuid
        );

        $response->assertJsonPath(
            'data.payment_no',
            $payment->payment_no
        );
    }

    public function test_payment_can_be_updated(): void
    {
        $customer = $this->createCustomer();

        $payment = Payment::query()->create(
            $this->validPayment($customer)
        );

        $response = $this->putJson(
            route(
                'payments.update',
                $payment
            ),
            [
                'payment_no' => 'REC-UPDATED',
                'payment_type' => PaymentTypeEnum::RECEIPT->value,
                'purpose' => PaymentPurposeEnum::CUSTOMER_PAYMENT->value,
                'payment_date' => '2026-09-29',
                'party_type' =>
                PaymentPartyTypeEnum::CUSTOMER->value,
                'party_id' => $customer->id,
                'account_id' => $this->cashAccount->id,
                'amount' => 15000,
                'payment_method' =>
                PaymentMethodEnum::BANK_TRANSFER->value,
                'reference_no' => 'REF-001',
                'notes' => 'Updated payment',
            ]
        );

        $response->assertSuccessful();

        $payment->refresh();

        $this->assertSame(
            'REC-UPDATED',
            $payment->payment_no
        );

        $this->assertEquals(
            15000,
            $payment->amount
        );

        $this->assertSame(
            'Updated payment',
            $payment->notes
        );

        $response->assertJsonPath(
            'data.uuid',
            $payment->uuid
        );
    }

    public function test_payment_can_be_deleted(): void
    {
        $customer = $this->createCustomer();

        $payment = Payment::query()->create(
            $this->validPayment($customer)
        );

        $response = $this->deleteJson(
            route(
                'payments.destroy',
                $payment
            )
        );

        $response->assertSuccessful();

        $this->assertSoftDeleted(
            'payments',
            [
                'id' => $payment->id,
            ]
        );
    }

    public function test_duplicate_payment_number_is_rejected(): void
    {
        $customer = $this->createCustomer();

        $data = $this->validPayment($customer);

        Payment::query()->create($data);

        unset($data['status']);

        $response = $this->postJson(
            route('payments.store'),
            $data
        );

        $response->assertStatus(422);

        $response->assertJson([
            'success' => false,
        ]);

        $response->assertJsonValidationErrors([
            'payment_no',
        ]);
    }

    public function test_payment_allocation_can_be_returned(): void
    {
        $customer = $this->createCustomer();

        $payment = Payment::query()->create(
            $this->validPayment($customer)
        );

        PaymentAllocation::query()->create([
            'payment_id' => $payment->id,
            'document_type' => 'customer_opening_bill',
            'document_id' => 100,
            'allocated_amount' => 4000,
        ]);

        $payment->load('allocations');

        $response = $this->getJson(
            route(
                'payments.show',
                $payment
            )
        );

        $response->assertSuccessful();
    }

    public function test_payment_can_be_created_with_allocations(): void
    {
        $customer = $this->createCustomer();

        $data = $this->validPayment(
            $customer,
            [
                'amount' => 10000,
                'allocations' => [
                    [
                        'document_type' => 'customer_opening_bill',
                        'document_id' => 100,
                        'allocated_amount' => 4000,
                    ],
                    [
                        'document_type' => 'customer_opening_bill',
                        'document_id' => 101,
                        'allocated_amount' => 3000,
                    ],
                ],
            ]
        );

        unset($data['status']);

        $response = $this->postJson(
            route('payments.store'),
            $data
        );

        $response->assertCreated();

        app(\App\Core\Tenant\TenantManager::class)
            ->setTenant($this->tenant);

        $payment = Payment::query()
            ->where(
                'payment_no',
                $data['payment_no']
            )
            ->firstOrFail();

        $this->assertCount(
            2,
            $payment->allocations
        );

        $this->assertEquals(
            7000,
            $payment->allocations->sum(
                'allocated_amount'
            )
        );

        $response->assertJsonCount(
            2,
            'data.allocations'
        );
    }

    public function test_payment_allocations_can_be_replaced_on_update(): void
    {
        $customer = $this->createCustomer();

        $payment = Payment::query()->create(
            $this->validPayment(
                $customer,
                [
                    'amount' => 10000,
                ]
            )
        );

        PaymentAllocation::query()->create([
            'payment_id' => $payment->id,
            'document_type' => 'customer_opening_bill',
            'document_id' => 100,
            'allocated_amount' => 4000,
        ]);

        $response = $this->putJson(
            route(
                'payments.update',
                $payment
            ),
            [
                'payment_no' => $payment->payment_no,
                'payment_type' => PaymentTypeEnum::RECEIPT->value,
                'purpose' => PaymentPurposeEnum::CUSTOMER_PAYMENT->value,
                'payment_date' =>
                $payment->payment_date->toDateString(),
                'party_type' =>
                PaymentPartyTypeEnum::CUSTOMER->value,
                'party_id' => $customer->id,
                'account_id' => $this->cashAccount->id,
                'amount' => 10000,
                'payment_method' =>
                PaymentMethodEnum::CASH->value,
                'reference_no' => null,
                'notes' => 'Updated allocations',
                'allocations' => [
                    [
                        'document_type' =>
                        'customer_opening_bill',
                        'document_id' => 101,
                        'allocated_amount' => 6000,
                    ],
                    [
                        'document_type' =>
                        'customer_opening_bill',
                        'document_id' => 102,
                        'allocated_amount' => 2000,
                    ],
                ],
            ]
        );

        $response->assertSuccessful();

        $payment->refresh();

        app(\App\Core\Tenant\TenantManager::class)
            ->setTenant($this->tenant);

        $this->assertCount(
            2,
            $payment->allocations
        );

        $this->assertDatabaseMissing(
            'payment_allocations',
            [
                'payment_id' => $payment->id,
                'document_id' => 100,
            ]
        );

        $this->assertDatabaseHas(
            'payment_allocations',
            [
                'payment_id' => $payment->id,
                'document_id' => 101,
                'allocated_amount' => 6000,
            ]
        );

        $this->assertDatabaseHas(
            'payment_allocations',
            [
                'payment_id' => $payment->id,
                'document_id' => 102,
                'allocated_amount' => 2000,
            ]
        );

        $response->assertJsonCount(
            2,
            'data.allocations'
        );
    }

    public function test_payment_allocations_can_be_cleared_on_update(): void
    {
        $customer = $this->createCustomer();

        $payment = Payment::query()->create(
            $this->validPayment(
                $customer,
                [
                    'amount' => 10000,
                ]
            )
        );

        PaymentAllocation::query()->create([
            'payment_id' => $payment->id,
            'document_type' => 'customer_opening_bill',
            'document_id' => 100,
            'allocated_amount' => 4000,
        ]);

        $response = $this->putJson(
            route(
                'payments.update',
                $payment
            ),
            [
                'payment_no' => $payment->payment_no,
                'payment_type' => PaymentTypeEnum::RECEIPT->value,
                'purpose' => PaymentPurposeEnum::CUSTOMER_PAYMENT->value,
                'payment_date' =>
                $payment->payment_date->toDateString(),
                'party_type' =>
                PaymentPartyTypeEnum::CUSTOMER->value,
                'party_id' => $customer->id,
                'account_id' => $this->cashAccount->id,
                'amount' => 10000,
                'payment_method' =>
                PaymentMethodEnum::CASH->value,
                'reference_no' => null,
                'notes' => 'Clear allocations',
                'allocations' => [],
            ]
        );

        $response->assertSuccessful();

        $this->assertDatabaseMissing(
            'payment_allocations',
            [
                'payment_id' => $payment->id,
            ]
        );

        $response->assertJsonCount(
            0,
            'data.allocations'
        );
    }

    public function test_payment_allocations_are_preserved_when_omitted_on_update(): void
    {
        $customer = $this->createCustomer();

        $payment = Payment::query()->create(
            $this->validPayment(
                $customer,
                [
                    'amount' => 10000,
                ]
            )
        );

        PaymentAllocation::query()->create([
            'payment_id' => $payment->id,
            'document_type' => 'customer_opening_bill',
            'document_id' => 100,
            'allocated_amount' => 4000,
        ]);

        $response = $this->putJson(
            route(
                'payments.update',
                $payment
            ),
            [
                'payment_no' => $payment->payment_no,
                'payment_type' => PaymentTypeEnum::RECEIPT->value,
                'purpose' => PaymentPurposeEnum::CUSTOMER_PAYMENT->value,
                'payment_date' =>
                $payment->payment_date->toDateString(),
                'party_type' =>
                PaymentPartyTypeEnum::CUSTOMER->value,
                'party_id' => $customer->id,
                'account_id' => $this->cashAccount->id,
                'amount' => 10000,
                'payment_method' =>
                PaymentMethodEnum::BANK_TRANSFER->value,
                'reference_no' => 'REF-002',
                'notes' => 'Keep allocation',
            ]
        );

        $response->assertSuccessful();

        $payment->refresh();

        app(\App\Core\Tenant\TenantManager::class)
            ->setTenant($this->tenant);

        $this->assertCount(
            1,
            $payment->allocations
        );

        $this->assertEquals(
            4000,
            $payment->allocations->first()
                ->allocated_amount
        );

        $response->assertJsonCount(
            1,
            'data.allocations'
        );
    }
}
