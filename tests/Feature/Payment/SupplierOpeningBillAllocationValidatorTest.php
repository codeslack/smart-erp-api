<?php

namespace Tests\Feature\Payment;

use Tests\TestCase;

use Illuminate\Support\Str;
use Illuminate\Foundation\Testing\RefreshDatabase;

use Tests\Support\CreatesTenant;
use Tests\Support\CreatesSupplier;

use App\Core\Enums\DocumentStatusEnum;
use App\Core\Enums\OpeningBalanceTypeEnum;
use App\Core\Tenant\TenantManager;

use App\Modules\Payment\Enums\PaymentMethodEnum;
use App\Modules\Payment\Enums\PaymentPartyTypeEnum;
use App\Modules\Payment\Enums\PaymentTypeEnum;
use App\Modules\Payment\Models\Payment;
use App\Modules\Payment\Models\PaymentAllocation;
use App\Modules\Payment\Services\Validation\Contracts\SupplierOpeningBillAllocationValidatorInterface;
use App\Modules\SupplierOpeningBill\Models\SupplierOpeningBill;

class SupplierOpeningBillAllocationValidatorTest extends TestCase
{
    use RefreshDatabase;
    use CreatesTenant;
    use CreatesSupplier;

    protected $tenant;
    protected $supplier;
    protected $payment;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = $this->createTestTenant();

        $this->supplier = $this->createSupplier();

        $this->payment = Payment::create([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'payment_no' => 'PAY-ALLOC-001',
            'payment_type' => PaymentTypeEnum::PAYMENT,
            'payment_date' => now()->toDateString(),
            'party_type' => PaymentPartyTypeEnum::SUPPLIER,
            'party_id' => $this->supplier->id,
            'account_id' => 1,
            'amount' => 1000,
            'payment_method' => PaymentMethodEnum::CASH,
            'status' => DocumentStatusEnum::DRAFT->value,
        ]);
    }

    public function test_valid_supplier_opening_bill_allocation_is_accepted(): void
    {
        $bill = $this->createOpeningBill(
            $this->supplier->id,
            500,
            500
        );

        $allocation = $this->createAllocation(
            $bill->id,
            300
        );

        app(SupplierOpeningBillAllocationValidatorInterface::class)
            ->validate($this->payment, $allocation);

        $this->assertTrue(true);
    }

    public function test_missing_supplier_opening_bill_is_rejected(): void
    {
        $allocation = $this->createAllocation(
            999999,
            300
        );

        $this->expectExceptionMessage(
            'Supplier opening bill not found.'
        );

        app(SupplierOpeningBillAllocationValidatorInterface::class)
            ->validate($this->payment, $allocation);
    }

    public function test_opening_bill_belonging_to_another_supplier_is_rejected(): void
    {
        $otherSupplier = $this->createSupplier();

        $bill = $this->createOpeningBill(
            $otherSupplier->id,
            500,
            500
        );

        $allocation = $this->createAllocation(
            $bill->id,
            300
        );

        $this->expectExceptionMessage(
            'Supplier opening bill does not belong to payment supplier.'
        );

        app(SupplierOpeningBillAllocationValidatorInterface::class)
            ->validate($this->payment, $allocation);
    }

    public function test_allocation_exceeding_supplier_opening_bill_balance_is_rejected(): void
    {
        $bill = $this->createOpeningBill(
            $this->supplier->id,
            500,
            200
        );

        $allocation = $this->createAllocation(
            $bill->id,
            300
        );

        $this->expectExceptionMessage(
            'Payment allocation exceeds supplier opening bill balance.'
        );

        app(SupplierOpeningBillAllocationValidatorInterface::class)
            ->validate($this->payment, $allocation);
    }

    public function test_cross_tenant_supplier_opening_bill_is_rejected(): void
    {
        $otherTenant = $this->createTestTenant();

        app(TenantManager::class)
            ->setTenant($otherTenant);

        $otherSupplier = $this->createSupplier([
            'tenant_id' => $otherTenant->id,
        ]);

        $bill = $this->createOpeningBill(
            $otherSupplier->id,
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
            'Supplier opening bill not found.'
        );

        app(SupplierOpeningBillAllocationValidatorInterface::class)
            ->validate($this->payment, $allocation);
    }

    protected function createOpeningBill(
        int $supplierId,
        float $amount,
        float $balanceAmount
    ): SupplierOpeningBill {
        return SupplierOpeningBill::create([
            'tenant_id' => tenantId(),
            'supplier_id' => $supplierId,
            'bill_no' => 'OB-' . Str::upper(Str::random(8)),
            'bill_date' => now()->toDateString(),
            'amount' => $amount,
            'balance_amount' => $balanceAmount,
            'balance_type' => OpeningBalanceTypeEnum::CREDIT->value,
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
            'document_type' => 'supplier_opening_bill',
            'document_id' => $documentId,
            'allocated_amount' => $amount,
        ]);
    }
}