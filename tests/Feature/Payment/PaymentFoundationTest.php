<?php

namespace Tests\Feature\Payment;

use Tests\TestCase;

use Illuminate\Foundation\Testing\RefreshDatabase;

use Tests\Support\CreatesTenant;
use Tests\Support\CreatesUser;

use App\Modules\Tenant\Models\Tenant;
use App\Modules\User\Models\User;
use App\Modules\Payment\Models\Payment;
use App\Modules\Payment\Models\PaymentAllocation;
use App\Modules\Payment\Enums\PaymentTypeEnum;
use App\Modules\Payment\Enums\PaymentMethodEnum;
use App\Modules\Payment\Enums\PaymentPartyTypeEnum;
use App\Modules\Payment\Services\Contracts\PaymentServiceInterface;
use App\Modules\Payment\Services\Contracts\PaymentAllocationServiceInterface;

class PaymentFoundationTest extends TestCase
{
    use RefreshDatabase;
    use CreatesTenant;
    use CreatesUser;

    protected Tenant $tenant;
    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = $this->createTestTenant();

        $this->user = $this->createTestUser();

        $this->authenticate();
    }

    protected function authenticate(): void
    {
        $user = $this->tenant->users()->first();

        $this->actingAs(
            $user,
            'sanctum'
        );
    }

    protected function paymentData(
        array $overrides = []
    ): array {
        return array_merge([
            'payment_no' => 'REC-0001',
            'payment_type' => PaymentTypeEnum::RECEIPT,
            'payment_date' => now()->toDateString(),
            'party_type' => PaymentPartyTypeEnum::CUSTOMER,
            'party_id' => null,
            'account_id' => 1,
            'amount' => 10000,
            'payment_method' => PaymentMethodEnum::CASH,
            'reference_no' => null,
            'notes' => null,
            'status' => 'DRAFT',
        ], $overrides);
    }

    public function test_payment_can_be_created_through_service(): void
    {
        $service = app(PaymentServiceInterface::class);

        $payment = $service->create(
            $this->paymentData()
        );

        $this->assertInstanceOf(
            Payment::class,
            $payment
        );

        $this->assertDatabaseHas(
            'payments',
            [
                'id' => $payment->id,
                'tenant_id' => $this->tenant->id,
                'payment_no' => 'REC-0001',
                'amount' => '10000.0000',
            ]
        );
    }

    public function test_payment_can_be_found_by_uuid(): void
    {
        $payment = Payment::query()->create(
            $this->paymentData()
        );

        $service = app(PaymentServiceInterface::class);

        $found = $service->findByUuid(
            $payment->uuid
        );

        $this->assertSame(
            $payment->id,
            $found->id
        );
    }

    public function test_payment_can_be_found_by_payment_number(): void
    {
        Payment::query()->create(
            $this->paymentData()
        );

        $repository = app(
            \App\Modules\Payment\Repositories\Contracts\PaymentRepositoryInterface::class
        );

        $payment = $repository->findByPaymentNo(
            'REC-0001'
        );

        $this->assertNotNull($payment);

        $this->assertSame(
            'REC-0001',
            $payment->payment_no
        );
    }

    public function test_payment_allocation_can_be_created(): void
    {
        $payment = Payment::query()->create(
            $this->paymentData()
        );

        $service = app(
            PaymentAllocationServiceInterface::class
        );

        $allocation = $service->create([
            'payment_id' => $payment->id,
            'document_type' => 'customer_opening_bill',
            'document_id' => 100,
            'allocated_amount' => 4000,
        ]);

        $this->assertInstanceOf(
            PaymentAllocation::class,
            $allocation
        );

        $this->assertDatabaseHas(
            'payment_allocations',
            [
                'id' => $allocation->id,
                'tenant_id' => $this->tenant->id,
                'payment_id' => $payment->id,
                'document_type' => 'customer_opening_bill',
                'document_id' => 100,
                'allocated_amount' => '4000.0000',
            ]
        );
    }

    public function test_allocated_amount_is_calculated(): void
    {
        $payment = Payment::query()->create(
            $this->paymentData()
        );

        PaymentAllocation::query()->create([
            'payment_id' => $payment->id,
            'document_type' => 'customer_opening_bill',
            'document_id' => 100,
            'allocated_amount' => 4000,
        ]);

        PaymentAllocation::query()->create([
            'payment_id' => $payment->id,
            'document_type' => 'customer_opening_bill',
            'document_id' => 100,
            'allocated_amount' => 2500,
        ]);

        $service = app(
            PaymentAllocationServiceInterface::class
        );

        $amount = $service->getAllocatedAmount(
            'customer_opening_bill',
            100
        );

        $this->assertSame(
            6500.0,
            $amount
        );
    }

    public function test_allocations_can_be_retrieved_by_payment(): void
    {
        $payment = Payment::query()->create(
            $this->paymentData()
        );

        PaymentAllocation::query()->create([
            'payment_id' => $payment->id,
            'document_type' => 'customer_opening_bill',
            'document_id' => 100,
            'allocated_amount' => 4000,
        ]);

        PaymentAllocation::query()->create([
            'payment_id' => $payment->id,
            'document_type' => 'sales_invoice',
            'document_id' => 200,
            'allocated_amount' => 3000,
        ]);

        $service = app(
            PaymentAllocationServiceInterface::class
        );

        $allocations = $service->getByPaymentId(
            $payment->id
        );

        $this->assertCount(
            2,
            $allocations
        );
    }
}