<?php

namespace Tests\Feature\Payment;

use Tests\TestCase;

use Illuminate\Support\Str;
use Illuminate\Foundation\Testing\RefreshDatabase;

use Tests\Support\CreatesTenant;
use Tests\Support\CreatesCustomer;


use App\Core\Enums\DocumentStatusEnum;
use App\Core\Enums\OpeningBalanceTypeEnum;
use App\Core\Tenant\TenantManager;
use App\Modules\CustomerOpeningBill\Models\CustomerOpeningBill;
use App\Modules\Payment\Enums\PaymentMethodEnum;
use App\Modules\Payment\Enums\PaymentPartyTypeEnum;
use App\Modules\Payment\Enums\PaymentPurposeEnum;
use App\Modules\Payment\Enums\PaymentTypeEnum;
use App\Modules\Payment\Models\Payment;
use App\Modules\Payment\Models\PaymentAllocation;
use App\Modules\Payment\Services\Validation\Contracts\PaymentAllocationValidatorInterface;

class PaymentAllocationValidatorTest extends TestCase
{
    use RefreshDatabase;
    use CreatesTenant;
    use CreatesCustomer;

    protected $tenant;
    protected $customer;
    protected $payment;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = $this->createTestTenant();

        $this->customer = $this->createCustomer();

        $this->payment = Payment::create([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'payment_no' => 'RCT-ALLOC-001',
            'payment_type' => PaymentTypeEnum::RECEIPT,
            'purpose' => PaymentPurposeEnum::CUSTOMER_PAYMENT,
            'payment_date' => now()->toDateString(),
            'party_type' => PaymentPartyTypeEnum::CUSTOMER,
            'party_id' => $this->customer->id,
            'account_id' => 1,
            'amount' => 1000,
            'payment_method' => PaymentMethodEnum::CASH,
            'status' => DocumentStatusEnum::DRAFT->value,
        ]);
    }

    public function test_valid_customer_opening_bill_allocation_is_accepted(): void
    {
        $bill = $this->createOpeningBill(
            $this->customer->id,
            500,
            500
        );

        $allocation = $this->createAllocation(
            $bill->id,
            300
        );

        app(PaymentAllocationValidatorInterface::class)
            ->validate($this->payment, $allocation);

        $this->assertTrue(true);
    }

    public function test_missing_customer_opening_bill_is_rejected(): void
    {
        $allocation = $this->createAllocation(
            999999,
            300
        );

        $this->expectExceptionMessage(
            'Customer opening bill not found.'
        );

        app(PaymentAllocationValidatorInterface::class)
            ->validate($this->payment, $allocation);
    }

    public function test_opening_bill_belonging_to_another_customer_is_rejected(): void
    {
        $otherCustomer = $this->createCustomer();

        $bill = $this->createOpeningBill(
            $otherCustomer->id,
            500,
            500
        );

        $allocation = $this->createAllocation(
            $bill->id,
            300
        );

        $this->expectExceptionMessage(
            'Customer opening bill does not belong to payment customer.'
        );

        app(PaymentAllocationValidatorInterface::class)
            ->validate($this->payment, $allocation);
    }

    public function test_allocation_exceeding_opening_bill_balance_is_rejected(): void
    {
        $bill = $this->createOpeningBill(
            $this->customer->id,
            500,
            200
        );

        $allocation = $this->createAllocation(
            $bill->id,
            300
        );

        $this->expectExceptionMessage(
            'Payment allocation exceeds opening bill balance.'
        );

        app(PaymentAllocationValidatorInterface::class)
            ->validate($this->payment, $allocation);
    }

    public function test_cross_tenant_opening_bill_is_rejected(): void
    {
        $otherTenant = $this->createTestTenant();

        app(TenantManager::class)
            ->setTenant($otherTenant);

        $otherCustomer = $this->createCustomer([
            'tenant_id' => $otherTenant->id,
        ]);

        $bill = $this->createOpeningBill(
            $otherCustomer->id,
            500,
            500
        );

        app(TenantManager::class)
            ->setTenant($this->tenant);

        $allocation = $this->createAllocation(
            $bill->id,
            300
        );

        $this->expectExceptionMessage(
            'Customer opening bill not found.'
        );

        app(PaymentAllocationValidatorInterface::class)
            ->validate($this->payment, $allocation);
    }

    protected function createOpeningBill(
        int $customerId,
        float $amount,
        float $balanceAmount
    ): CustomerOpeningBill {
        return CustomerOpeningBill::create([
            'tenant_id' => tenantId(),
            'customer_id' => $customerId,
            'bill_no' => 'OB-' . Str::upper(Str::random(8)),
            'bill_date' => now()->toDateString(),
            'amount' => $amount,
            'balance_amount' => $balanceAmount,
            'balance_type' => OpeningBalanceTypeEnum::DEBIT->value,
        ]);
    }

    protected function createAllocation(
        int $documentId,
        float $amount
    ): PaymentAllocation {
        return PaymentAllocation::create([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => tenantId(),
            'payment_id' => $this->payment->id,
            'document_type' => 'customer_opening_bill',
            'document_id' => $documentId,
            'allocated_amount' => $amount,
        ]);
    }
}
