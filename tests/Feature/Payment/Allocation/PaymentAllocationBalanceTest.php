<?php

namespace Tests\Feature\Payment\Allocation;

use Tests\TestCase;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

use Tests\Support\CreatesTenant;
use Tests\Support\CreatesCustomer;
use Tests\Support\CreatesSupplier;
use Tests\Support\CreatesWarehouse;
use Tests\Support\CreatesProduct;

use App\Core\Tenant\TenantManager;
use App\Core\Enums\OpeningBalanceTypeEnum;
use App\Core\Enums\DocumentStatusEnum;

use App\Modules\Tenant\Models\Tenant;
use App\Modules\Customer\Models\Customer;
use App\Modules\Supplier\Models\Supplier;
use App\Modules\Product\Enums\ProductTypeEnum;
use App\Modules\Product\Models\Product;
use App\Modules\Warehouse\Models\Warehouse;
use App\Modules\Sales\Services\SaleService;
use App\Modules\SalesReturn\Models\SalesReturn;
use App\Modules\SalesReturn\Enums\SalesReturnRefundType;
use App\Modules\SalesReturn\Enums\SalesReturnStatus;
use App\Modules\Purchase\Services\PurchaseService;
use App\Modules\PurchaseReturn\Models\PurchaseReturn;
use App\Modules\PurchaseReturn\Enums\PurchaseReturnRefundType;
use App\Modules\PurchaseReturn\Enums\PurchaseReturnStatus;

use App\Modules\Accounting\Enums\AccountingAccounts;
use App\Modules\Accounting\Models\ChartOfAccount;
use App\Modules\Accounting\Services\AccountingSetupService;

use App\Modules\CustomerOpeningBill\Models\CustomerOpeningBill;
use App\Modules\SupplierOpeningBill\Models\SupplierOpeningBill;

use App\Modules\Payment\Enums\PaymentTypeEnum;
use App\Modules\Payment\Enums\PaymentMethodEnum;
use App\Modules\Payment\Enums\PaymentPartyTypeEnum;
use App\Modules\Payment\Enums\PaymentPurposeEnum;

use App\Modules\Payment\Services\Contracts\PaymentServiceInterface;

class PaymentAllocationBalanceTest extends TestCase
{
    use RefreshDatabase;
    use CreatesTenant;
    use CreatesCustomer;
    use CreatesSupplier;
    use CreatesWarehouse;
    use CreatesProduct;

    protected Tenant $tenant;

    protected ChartOfAccount $cashAccount;

    protected Customer $customer;

    protected Supplier $supplier;

    protected Warehouse $warehouse;

    protected Product $serviceProduct;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = $this->createTestTenant();

        app(TenantManager::class)
            ->setTenant($this->tenant);

        app(AccountingSetupService::class)
            ->setup($this->tenant);

        $this->cashAccount = ChartOfAccount::query()
            ->where(
                'tenant_id',
                $this->tenant->id
            )
            ->where(
                'account_code',
                AccountingAccounts::CASH
            )
            ->firstOrFail();

        $this->customer = $this->createCustomer();

        $this->supplier = $this->createSupplier();
        $this->warehouse = $this->createWarehouse();
        $this->serviceProduct = $this->createProduct([
            'product_type' => ProductTypeEnum::SERVICE,
        ]);
    }

    public function test_customer_opening_bill_balance_decreases_on_receipt_confirmation_and_restores_on_reversal(): void
    {
        $openingBill = CustomerOpeningBill::create([
            'tenant_id' => $this->tenant->id,
            'uuid' => (string) \Illuminate\Support\Str::uuid(),
            'customer_id' => $this->customer->id,
            'bill_no' => 'OB-CUST-001',
            'bill_date' => '2026-01-01',
            'due_date' => '2026-01-31',
            'amount' => 10000,
            'balance_amount' => 10000,
            'balance_type' => OpeningBalanceTypeEnum::DEBIT,
            'notes' => 'Payment allocation balance test',
        ]);

        $payment = app(PaymentServiceInterface::class)
            ->create([
                'uuid' => (string) \Illuminate\Support\Str::uuid(),
                'tenant_id' => $this->tenant->id,
                'payment_no' => 'REC-BAL-001',
                'payment_type' => PaymentTypeEnum::RECEIPT->value,
                'purpose' => PaymentPurposeEnum::CUSTOMER_PAYMENT->value,
                'payment_date' => '2026-01-10',
                'party_type' => PaymentPartyTypeEnum::CUSTOMER->value,
                'party_id' => $this->customer->id,
                'account_id' => $this->cashAccount->id,
                'amount' => 6000,
                'payment_method' => PaymentMethodEnum::CASH->value,
                'notes' => 'Customer opening bill allocation test',
                'allocations' => [
                    [
                        'document_type' => 'customer_opening_bill',
                        'document_id' => $openingBill->id,
                        'allocated_amount' => 6000,
                    ],
                ],
            ]);

        $this->assertDatabaseHas('payment_allocations', [
            'payment_id' => $payment->id,
            'document_type' => 'customer_opening_bill',
            'document_id' => $openingBill->id,
            'allocated_amount' => 6000,
        ]);

        $this->assertCount(
            1,
            $payment->allocations
        );

        $this->assertSame(
            DocumentStatusEnum::DRAFT->value,
            $payment->status
        );

        $this->assertSame(
            '10000.0000',
            (string) $openingBill->refresh()->balance_amount
        );

        $allocation = $payment->allocations->first();

        $this->assertNotNull($allocation);

        $this->assertSame(
            '6000.0000',
            (string) $allocation->allocated_amount
        );

        $payment = app(PaymentServiceInterface::class)
            ->confirm($payment);

        $this->assertSame(
            DocumentStatusEnum::CONFIRMED->value,
            $payment->status
        );

        $openingBill->refresh();

        $this->assertEquals(
            '4000.0000',
            $openingBill->balance_amount
        );

        $this->assertDatabaseHas(
            'payment_allocations',
            [
                'payment_id' => $payment->id,
                'document_type' => 'customer_opening_bill',
                'document_id' => $openingBill->id,
                'allocated_amount' => 6000,
            ]
        );

        $payment = app(PaymentServiceInterface::class)
            ->reverse($payment);

        $this->assertSame(
            DocumentStatusEnum::REVERSED->value,
            $payment->status
        );

        $openingBill->refresh();

        $this->assertEquals(
            '10000.0000',
            $openingBill->balance_amount
        );

        $this->assertDatabaseHas(
            'payment_allocations',
            [
                'payment_id' => $payment->id,
                'document_type' => 'customer_opening_bill',
                'document_id' => $openingBill->id,
                'allocated_amount' => 6000,
            ]
        );
    }

    public function test_supplier_opening_bill_balance_decreases_on_payment_confirmation_and_restores_on_reversal(): void
    {
        $openingBill = SupplierOpeningBill::create([
            'tenant_id' => $this->tenant->id,
            'uuid' => (string) \Illuminate\Support\Str::uuid(),
            'supplier_id' => $this->supplier->id,
            'bill_no' => 'OB-SUP-001',
            'bill_date' => '2026-01-01',
            'due_date' => '2026-01-31',
            'amount' => 10000,
            'balance_amount' => 10000,
            'balance_type' => OpeningBalanceTypeEnum::CREDIT,
            'notes' => 'Supplier payment allocation balance test',
        ]);

        $payment = app(PaymentServiceInterface::class)->create([
            'uuid' => (string) \Illuminate\Support\Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'payment_no' => 'PAY-BAL-001',
            'payment_type' => PaymentTypeEnum::PAYMENT->value,
            'purpose' => PaymentPurposeEnum::SUPPLIER_PAYMENT->value,
            'payment_date' => '2026-01-10',
            'party_type' => PaymentPartyTypeEnum::SUPPLIER->value,
            'party_id' => $this->supplier->id,
            'account_id' => $this->cashAccount->id,
            'amount' => 6000,
            'payment_method' => PaymentMethodEnum::CASH->value,
            'notes' => 'Supplier opening bill allocation test',
            'allocations' => [
                [
                    'document_type' => 'supplier_opening_bill',
                    'document_id' => $openingBill->id,
                    'allocated_amount' => 6000,
                ],
            ],
        ]);

        $this->assertDatabaseHas('payment_allocations', [
            'payment_id' => $payment->id,
            'document_type' => 'supplier_opening_bill',
            'document_id' => $openingBill->id,
            'allocated_amount' => 6000,
        ]);

        $this->assertSame(
            DocumentStatusEnum::DRAFT->value,
            $payment->status
        );

        $payment = app(PaymentServiceInterface::class)->confirm(
            $payment
        );

        $this->assertSame(
            DocumentStatusEnum::CONFIRMED->value,
            $payment->status
        );

        $openingBill->refresh();

        $this->assertEquals(
            '4000.0000',
            $openingBill->balance_amount
        );

        $payment = app(PaymentServiceInterface::class)->reverse(
            $payment
        );

        $this->assertSame(
            DocumentStatusEnum::REVERSED->value,
            $payment->status
        );

        $openingBill->refresh();

        $this->assertEquals(
            '10000.0000',
            $openingBill->balance_amount
        );

        $this->assertDatabaseHas('payment_allocations', [
            'payment_id' => $payment->id,
            'document_type' => 'supplier_opening_bill',
            'document_id' => $openingBill->id,
            'allocated_amount' => 6000,
        ]);
    }

    public function test_customer_receipt_can_allocate_across_multiple_opening_bills_and_reverse(): void
    {
        $firstBill = CustomerOpeningBill::create([
            'tenant_id' => $this->tenant->id,
            'uuid' => (string) \Illuminate\Support\Str::uuid(),
            'customer_id' => $this->customer->id,
            'bill_no' => 'OB-CUST-002',
            'bill_date' => '2026-01-01',
            'due_date' => '2026-01-31',
            'amount' => 10000,
            'balance_amount' => 10000,
            'balance_type' => OpeningBalanceTypeEnum::DEBIT,
            'notes' => 'Multiple allocation test - first bill',
        ]);

        $secondBill = CustomerOpeningBill::create([
            'tenant_id' => $this->tenant->id,
            'uuid' => (string) \Illuminate\Support\Str::uuid(),
            'customer_id' => $this->customer->id,
            'bill_no' => 'OB-CUST-003',
            'bill_date' => '2026-01-02',
            'due_date' => '2026-02-01',
            'amount' => 8000,
            'balance_amount' => 8000,
            'balance_type' => OpeningBalanceTypeEnum::DEBIT,
            'notes' => 'Multiple allocation test - second bill',
        ]);

        $payment = app(PaymentServiceInterface::class)->create([
            'uuid' => (string) \Illuminate\Support\Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'payment_no' => 'REC-MULTI-001',
            'payment_type' => PaymentTypeEnum::RECEIPT->value,
            'purpose' => PaymentPurposeEnum::CUSTOMER_PAYMENT->value,
            'payment_date' => '2026-01-10',
            'party_type' => PaymentPartyTypeEnum::CUSTOMER->value,
            'party_id' => $this->customer->id,
            'account_id' => $this->cashAccount->id,
            'amount' => 12000,
            'payment_method' => PaymentMethodEnum::CASH->value,
            'notes' => 'Multiple customer opening bill allocation test',
            'allocations' => [
                [
                    'document_type' => 'customer_opening_bill',
                    'document_id' => $firstBill->id,
                    'allocated_amount' => 7000,
                ],
                [
                    'document_type' => 'customer_opening_bill',
                    'document_id' => $secondBill->id,
                    'allocated_amount' => 5000,
                ],
            ],
        ]);

        $this->assertCount(
            2,
            $payment->allocations
        );

        $payment = app(PaymentServiceInterface::class)->confirm(
            $payment
        );

        $this->assertSame(
            DocumentStatusEnum::CONFIRMED->value,
            $payment->status
        );

        $firstBill->refresh();
        $secondBill->refresh();

        $this->assertEquals(
            '3000.0000',
            $firstBill->balance_amount
        );

        $this->assertEquals(
            '3000.0000',
            $secondBill->balance_amount
        );

        $payment = app(PaymentServiceInterface::class)->reverse(
            $payment
        );

        $this->assertSame(
            DocumentStatusEnum::REVERSED->value,
            $payment->status
        );

        $firstBill->refresh();
        $secondBill->refresh();

        $this->assertEquals(
            '10000.0000',
            $firstBill->balance_amount
        );

        $this->assertEquals(
            '8000.0000',
            $secondBill->balance_amount
        );
    }

    public function test_supplier_payment_can_allocate_across_multiple_opening_bills_and_reverse(): void
    {
        $firstBill = SupplierOpeningBill::create([
            'tenant_id' => $this->tenant->id,
            'uuid' => (string) \Illuminate\Support\Str::uuid(),
            'supplier_id' => $this->supplier->id,
            'bill_no' => 'OB-SUP-002',
            'bill_date' => '2026-01-01',
            'due_date' => '2026-01-31',
            'amount' => 10000,
            'balance_amount' => 10000,
            'balance_type' => OpeningBalanceTypeEnum::CREDIT,
            'notes' => 'Multiple allocation test - first bill',
        ]);

        $secondBill = SupplierOpeningBill::create([
            'tenant_id' => $this->tenant->id,
            'uuid' => (string) \Illuminate\Support\Str::uuid(),
            'supplier_id' => $this->supplier->id,
            'bill_no' => 'OB-SUP-003',
            'bill_date' => '2026-01-02',
            'due_date' => '2026-02-01',
            'amount' => 8000,
            'balance_amount' => 8000,
            'balance_type' => OpeningBalanceTypeEnum::CREDIT,
            'notes' => 'Multiple allocation test - second bill',
        ]);

        $payment = app(PaymentServiceInterface::class)->create([
            'uuid' => (string) \Illuminate\Support\Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'payment_no' => 'PAY-MULTI-001',
            'payment_type' => PaymentTypeEnum::PAYMENT->value,
            'purpose' => PaymentPurposeEnum::SUPPLIER_PAYMENT->value,
            'payment_date' => '2026-01-10',
            'party_type' => PaymentPartyTypeEnum::SUPPLIER->value,
            'party_id' => $this->supplier->id,
            'account_id' => $this->cashAccount->id,
            'amount' => 12000,
            'payment_method' => PaymentMethodEnum::CASH->value,
            'notes' => 'Multiple supplier opening bill allocation test',
            'allocations' => [
                [
                    'document_type' => 'supplier_opening_bill',
                    'document_id' => $firstBill->id,
                    'allocated_amount' => 7000,
                ],
                [
                    'document_type' => 'supplier_opening_bill',
                    'document_id' => $secondBill->id,
                    'allocated_amount' => 5000,
                ],
            ],
        ]);

        $this->assertCount(
            2,
            $payment->allocations
        );

        $payment = app(PaymentServiceInterface::class)->confirm(
            $payment
        );

        $this->assertSame(
            DocumentStatusEnum::CONFIRMED->value,
            $payment->status
        );

        $firstBill->refresh();
        $secondBill->refresh();

        $this->assertEquals(
            '3000.0000',
            $firstBill->balance_amount
        );

        $this->assertEquals(
            '3000.0000',
            $secondBill->balance_amount
        );

        $payment = app(PaymentServiceInterface::class)->reverse(
            $payment
        );

        $this->assertSame(
            DocumentStatusEnum::REVERSED->value,
            $payment->status
        );

        $firstBill->refresh();
        $secondBill->refresh();

        $this->assertEquals(
            '10000.0000',
            $firstBill->balance_amount
        );

        $this->assertEquals(
            '8000.0000',
            $secondBill->balance_amount
        );
    }

    public function test_failed_payment_confirmation_does_not_change_supplier_opening_bill_balance(): void
    {
        $bill = SupplierOpeningBill::create([
            'tenant_id' => $this->tenant->id,
            'uuid' => (string) \Illuminate\Support\Str::uuid(),
            'supplier_id' => $this->supplier->id,
            'bill_no' => 'OB-SUP-ROLLBACK-001',
            'bill_date' => '2026-01-01',
            'due_date' => '2026-01-31',
            'amount' => 10000,
            'balance_amount' => 10000,
            'balance_type' => OpeningBalanceTypeEnum::CREDIT,
            'notes' => 'Rollback test',
        ]);

        $payment = app(PaymentServiceInterface::class)->create([
            'uuid' => (string) \Illuminate\Support\Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'payment_no' => 'PAY-ROLLBACK-001',
            'payment_type' => PaymentTypeEnum::PAYMENT->value,
            'purpose' => PaymentPurposeEnum::SUPPLIER_PAYMENT->value,
            'payment_date' => '2026-01-10',
            'party_type' => PaymentPartyTypeEnum::SUPPLIER->value,
            'party_id' => $this->supplier->id,
            'account_id' => $this->cashAccount->id,
            'amount' => 6000,
            'payment_method' => PaymentMethodEnum::CASH->value,
            'notes' => 'Rollback test',
            'allocations' => [
                [
                    'document_type' => 'supplier_opening_bill',
                    'document_id' => $bill->id,
                    'allocated_amount' => 6000,
                ],
            ],
        ]);

        $this->expectException(\App\Core\Exceptions\BusinessException::class);

        /*
     * Force confirmation to fail because the payment
     * references an invalid accounting condition.
     */
        $payment->update([
            'account_id' => 999999999,
        ]);

        try {
            app(PaymentServiceInterface::class)->confirm(
                $payment->fresh()
            );
        } finally {
            $bill->refresh();

            $this->assertEquals(
                '10000.0000',
                $bill->balance_amount
            );

            $this->assertSame(
                DocumentStatusEnum::DRAFT->value,
                $payment->fresh()->status
            );
        }
    }

    public function test_payment_allocation_total_cannot_exceed_payment_amount(): void
    {
        $firstBill = SupplierOpeningBill::create([
            'tenant_id' => $this->tenant->id,
            'uuid' => (string) \Illuminate\Support\Str::uuid(),
            'supplier_id' => $this->supplier->id,
            'bill_no' => 'OB-SUP-OVER-001',
            'bill_date' => '2026-01-01',
            'due_date' => '2026-01-31',
            'amount' => 10000,
            'balance_amount' => 10000,
            'balance_type' => OpeningBalanceTypeEnum::CREDIT,
            'notes' => 'Over allocation test',
        ]);

        $secondBill = SupplierOpeningBill::create([
            'tenant_id' => $this->tenant->id,
            'uuid' => (string) \Illuminate\Support\Str::uuid(),
            'supplier_id' => $this->supplier->id,
            'bill_no' => 'OB-SUP-OVER-002',
            'bill_date' => '2026-01-02',
            'due_date' => '2026-02-01',
            'amount' => 8000,
            'balance_amount' => 8000,
            'balance_type' => OpeningBalanceTypeEnum::CREDIT,
            'notes' => 'Over allocation test',
        ]);

        $payment = app(PaymentServiceInterface::class)->create([
            'uuid' => (string) \Illuminate\Support\Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'payment_no' => 'PAY-OVER-001',
            'payment_type' => PaymentTypeEnum::PAYMENT->value,
            'purpose' => PaymentPurposeEnum::SUPPLIER_PAYMENT->value,
            'payment_date' => '2026-01-10',
            'party_type' => PaymentPartyTypeEnum::SUPPLIER->value,
            'party_id' => $this->supplier->id,
            'account_id' => $this->cashAccount->id,
            'amount' => 10000,
            'payment_method' => PaymentMethodEnum::CASH->value,
            'notes' => 'Over allocation test',
            'allocations' => [
                [
                    'document_type' => 'supplier_opening_bill',
                    'document_id' => $firstBill->id,
                    'allocated_amount' => 7000,
                ],
                [
                    'document_type' => 'supplier_opening_bill',
                    'document_id' => $secondBill->id,
                    'allocated_amount' => 5000,
                ],
            ],
        ]);

        $this->expectException(\App\Core\Exceptions\BusinessException::class);

        try {
            app(PaymentServiceInterface::class)->confirm(
                $payment
            );
        } finally {
            $firstBill->refresh();
            $secondBill->refresh();

            $this->assertEquals(
                '10000.0000',
                $firstBill->balance_amount
            );

            $this->assertEquals(
                '8000.0000',
                $secondBill->balance_amount
            );

            $this->assertSame(
                DocumentStatusEnum::DRAFT->value,
                $payment->fresh()->status
            );
        }
    }

    public function test_allocation_cannot_exceed_opening_bill_balance(): void
    {
        $bill = SupplierOpeningBill::create([
            'tenant_id' => $this->tenant->id,
            'uuid' => (string) \Illuminate\Support\Str::uuid(),
            'supplier_id' => $this->supplier->id,
            'bill_no' => 'OB-SUP-BALANCE-001',
            'bill_date' => '2026-01-01',
            'due_date' => '2026-01-31',
            'amount' => 5000,
            'balance_amount' => 5000,
            'balance_type' => OpeningBalanceTypeEnum::CREDIT,
            'notes' => 'Opening bill balance validation test',
        ]);

        $payment = app(PaymentServiceInterface::class)->create([
            'uuid' => (string) \Illuminate\Support\Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'payment_no' => 'PAY-BALANCE-001',
            'payment_type' => PaymentTypeEnum::PAYMENT->value,
            'purpose' => PaymentPurposeEnum::SUPPLIER_PAYMENT->value,
            'payment_date' => '2026-01-10',
            'party_type' => PaymentPartyTypeEnum::SUPPLIER->value,
            'party_id' => $this->supplier->id,
            'account_id' => $this->cashAccount->id,
            'amount' => 8000,
            'payment_method' => PaymentMethodEnum::CASH->value,
            'notes' => 'Opening bill balance validation test',
            'allocations' => [
                [
                    'document_type' => 'supplier_opening_bill',
                    'document_id' => $bill->id,
                    'allocated_amount' => 6000,
                ],
            ],
        ]);

        $this->expectException(\App\Core\Exceptions\BusinessException::class);

        try {
            app(PaymentServiceInterface::class)->confirm(
                $payment
            );
        } finally {
            $bill->refresh();

            $this->assertEquals(
                '5000.0000',
                $bill->balance_amount
            );

            $this->assertSame(
                DocumentStatusEnum::DRAFT->value,
                $payment->fresh()->status
            );
        }
    }

    public function test_customer_receipt_allocation_cannot_exceed_opening_bill_balance(): void
    {
        $bill = CustomerOpeningBill::create([
            'tenant_id' => $this->tenant->id,
            'uuid' => (string) \Illuminate\Support\Str::uuid(),
            'customer_id' => $this->customer->id,
            'bill_no' => 'OB-CUS-BALANCE-001',
            'bill_date' => '2026-01-01',
            'due_date' => '2026-01-31',
            'amount' => 5000,
            'balance_amount' => 5000,
            'balance_type' => OpeningBalanceTypeEnum::DEBIT,
            'notes' => 'Opening bill balance validation test',
        ]);

        $payment = app(PaymentServiceInterface::class)->create([
            'uuid' => (string) \Illuminate\Support\Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'payment_no' => 'REC-BALANCE-001',
            'payment_type' => PaymentTypeEnum::RECEIPT->value,
            'purpose' => PaymentPurposeEnum::CUSTOMER_PAYMENT->value,
            'payment_date' => '2026-01-10',
            'party_type' => PaymentPartyTypeEnum::CUSTOMER->value,
            'party_id' => $this->customer->id,
            'account_id' => $this->cashAccount->id,
            'amount' => 8000,
            'payment_method' => PaymentMethodEnum::CASH->value,
            'notes' => 'Opening bill balance validation test',
            'allocations' => [
                [
                    'document_type' => 'customer_opening_bill',
                    'document_id' => $bill->id,
                    'allocated_amount' => 6000,
                ],
            ],
        ]);

        $this->expectException(\App\Core\Exceptions\BusinessException::class);

        try {
            app(PaymentServiceInterface::class)->confirm(
                $payment
            );
        } finally {
            $bill->refresh();

            $this->assertEquals(
                '5000.0000',
                $bill->balance_amount
            );

            $this->assertSame(
                DocumentStatusEnum::DRAFT->value,
                $payment->fresh()->status
            );
        }
    }

    public function test_supplier_payment_cannot_allocate_another_suppliers_opening_bill(): void
    {
        $otherSupplier = Supplier::create([
            'tenant_id' => $this->tenant->id,
            'uuid' => (string) \Illuminate\Support\Str::uuid(),
            'name' => 'Other Supplier',
            'code' => 'SUP-OTHER',
            'is_active' => true,
        ]);

        $bill = SupplierOpeningBill::create([
            'tenant_id' => $this->tenant->id,
            'uuid' => (string) \Illuminate\Support\Str::uuid(),
            'supplier_id' => $otherSupplier->id,
            'bill_no' => 'OB-SUP-PARTY-001',
            'bill_date' => '2026-01-01',
            'due_date' => '2026-01-31',
            'amount' => 10000,
            'balance_amount' => 10000,
            'balance_type' => OpeningBalanceTypeEnum::CREDIT,
            'notes' => 'Party validation test',
        ]);

        $payment = app(PaymentServiceInterface::class)->create([
            'uuid' => (string) \Illuminate\Support\Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'payment_no' => 'PAY-PARTY-001',
            'payment_type' => PaymentTypeEnum::PAYMENT->value,
            'purpose' => PaymentPurposeEnum::SUPPLIER_PAYMENT->value,
            'payment_date' => '2026-01-10',
            'party_type' => PaymentPartyTypeEnum::SUPPLIER->value,
            'party_id' => $this->supplier->id,
            'account_id' => $this->cashAccount->id,
            'amount' => 5000,
            'payment_method' => PaymentMethodEnum::CASH->value,
            'notes' => 'Party validation test',
            'allocations' => [
                [
                    'document_type' => 'supplier_opening_bill',
                    'document_id' => $bill->id,
                    'allocated_amount' => 5000,
                ],
            ],
        ]);

        $this->expectException(\App\Core\Exceptions\BusinessException::class);

        try {
            app(PaymentServiceInterface::class)->confirm(
                $payment
            );
        } finally {
            $bill->refresh();

            $this->assertEquals(
                '10000.0000',
                $bill->balance_amount
            );

            $this->assertSame(
                DocumentStatusEnum::DRAFT->value,
                $payment->fresh()->status
            );
        }
    }

    public function test_customer_receipt_cannot_allocate_another_customers_opening_bill(): void
    {
        $otherCustomer = Customer::create([
            'tenant_id' => $this->tenant->id,
            'uuid' => (string) \Illuminate\Support\Str::uuid(),
            'name' => 'Other Customer',
            'code' => 'CUS-OTHER',
            'is_active' => true,
        ]);

        $bill = CustomerOpeningBill::create([
            'tenant_id' => $this->tenant->id,
            'uuid' => (string) \Illuminate\Support\Str::uuid(),
            'customer_id' => $otherCustomer->id,
            'bill_no' => 'OB-CUS-PARTY-001',
            'bill_date' => '2026-01-01',
            'due_date' => '2026-01-31',
            'amount' => 10000,
            'balance_amount' => 10000,
            'balance_type' => OpeningBalanceTypeEnum::DEBIT,
            'notes' => 'Party validation test',
        ]);

        $payment = app(PaymentServiceInterface::class)->create([
            'uuid' => (string) \Illuminate\Support\Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'payment_no' => 'REC-PARTY-001',
            'payment_type' => PaymentTypeEnum::RECEIPT->value,
            'purpose' => PaymentPurposeEnum::CUSTOMER_PAYMENT->value,
            'payment_date' => '2026-01-10',
            'party_type' => PaymentPartyTypeEnum::CUSTOMER->value,
            'party_id' => $this->customer->id,
            'account_id' => $this->cashAccount->id,
            'amount' => 5000,
            'payment_method' => PaymentMethodEnum::CASH->value,
            'notes' => 'Party validation test',
            'allocations' => [
                [
                    'document_type' => 'customer_opening_bill',
                    'document_id' => $bill->id,
                    'allocated_amount' => 5000,
                ],
            ],
        ]);

        $this->expectException(\App\Core\Exceptions\BusinessException::class);

        try {
            app(PaymentServiceInterface::class)->confirm(
                $payment
            );
        } finally {
            $bill->refresh();

            $this->assertEquals(
                '10000.0000',
                $bill->balance_amount
            );

            $this->assertSame(
                DocumentStatusEnum::DRAFT->value,
                $payment->fresh()->status
            );
        }
    }

    public function test_customer_receipt_cannot_allocate_supplier_opening_bill(): void
    {
        $bill = SupplierOpeningBill::create([
            'tenant_id' => $this->tenant->id,
            'uuid' => (string) \Illuminate\Support\Str::uuid(),
            'supplier_id' => $this->supplier->id,
            'bill_no' => 'OB-SUP-TYPE-001',
            'bill_date' => '2026-01-01',
            'due_date' => '2026-01-31',
            'amount' => 10000,
            'balance_amount' => 10000,
            'balance_type' => OpeningBalanceTypeEnum::CREDIT,
            'notes' => 'Document type validation test',
        ]);

        $payment = app(PaymentServiceInterface::class)->create([
            'uuid' => (string) \Illuminate\Support\Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'payment_no' => 'REC-TYPE-001',
            'payment_type' => PaymentTypeEnum::RECEIPT->value,
            'purpose' => PaymentPurposeEnum::CUSTOMER_PAYMENT->value,
            'payment_date' => '2026-01-10',
            'party_type' => PaymentPartyTypeEnum::CUSTOMER->value,
            'party_id' => $this->customer->id,
            'account_id' => $this->cashAccount->id,
            'amount' => 5000,
            'payment_method' => PaymentMethodEnum::CASH->value,
            'notes' => 'Document type validation test',
            'allocations' => [
                [
                    'document_type' => 'supplier_opening_bill',
                    'document_id' => $bill->id,
                    'allocated_amount' => 5000,
                ],
            ],
        ]);

        $this->expectException(\App\Core\Exceptions\BusinessException::class);

        try {
            app(PaymentServiceInterface::class)->confirm(
                $payment
            );
        } finally {
            $bill->refresh();

            $this->assertEquals(
                '10000.0000',
                $bill->balance_amount
            );

            $this->assertSame(
                DocumentStatusEnum::DRAFT->value,
                $payment->fresh()->status
            );
        }
    }

    public function test_payment_allocated_amount_is_derived_from_allocation_records(): void
    {
        $firstBill = SupplierOpeningBill::create([
            'tenant_id' => $this->tenant->id,
            'uuid' => (string) \Illuminate\Support\Str::uuid(),
            'supplier_id' => $this->supplier->id,
            'bill_no' => 'OB-SUP-DERIVED-001',
            'bill_date' => '2026-01-01',
            'due_date' => '2026-01-31',
            'amount' => 10000,
            'balance_amount' => 10000,
            'balance_type' => OpeningBalanceTypeEnum::CREDIT,
        ]);

        $secondBill = SupplierOpeningBill::create([
            'tenant_id' => $this->tenant->id,
            'uuid' => (string) \Illuminate\Support\Str::uuid(),
            'supplier_id' => $this->supplier->id,
            'bill_no' => 'OB-SUP-DERIVED-002',
            'bill_date' => '2026-01-02',
            'due_date' => '2026-02-01',
            'amount' => 8000,
            'balance_amount' => 8000,
            'balance_type' => OpeningBalanceTypeEnum::CREDIT,
        ]);

        $payment = app(PaymentServiceInterface::class)->create([
            'uuid' => (string) \Illuminate\Support\Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'payment_no' => 'PAY-DERIVED-001',
            'payment_type' => PaymentTypeEnum::PAYMENT->value,
            'purpose' => PaymentPurposeEnum::SUPPLIER_PAYMENT->value,
            'payment_date' => '2026-01-10',
            'party_type' => PaymentPartyTypeEnum::SUPPLIER->value,
            'party_id' => $this->supplier->id,
            'account_id' => $this->cashAccount->id,
            'amount' => 12000,
            'payment_method' => PaymentMethodEnum::CASH->value,
            'notes' => 'Derived allocation test',
            'allocations' => [
                [
                    'document_type' => 'supplier_opening_bill',
                    'document_id' => $firstBill->id,
                    'allocated_amount' => 7000,
                ],
                [
                    'document_type' => 'supplier_opening_bill',
                    'document_id' => $secondBill->id,
                    'allocated_amount' => 5000,
                ],
            ],
        ]);

        $this->assertCount(2, $payment->allocations);

        $allocatedAmount = $payment->allocations->sum(
            fn($allocation) => (float) $allocation->allocated_amount
        );

        $advanceAmount = (float) $payment->amount - $allocatedAmount;

        $this->assertEquals(12000.0, $allocatedAmount);
        $this->assertEquals(0.0, $advanceAmount);

        $payment = app(PaymentServiceInterface::class)->confirm($payment);

        $payment->refresh();
        $payment->load('allocations');

        $this->assertCount(2, $payment->allocations);

        $this->assertEquals(
            12000.0,
            $payment->allocations->sum(
                fn($allocation) => (float) $allocation->allocated_amount
            )
        );

        $payment = app(PaymentServiceInterface::class)->reverse($payment);

        $payment->refresh();
        $payment->load('allocations');

        $this->assertCount(2, $payment->allocations);
    }
    public function test_sales_invoice_paid_and_due_balances_are_restored_when_customer_receipt_is_reversed(): void
    {
        $sale = app(SaleService::class)->create([
            'customer_uuid' => $this->customer->uuid,
            'warehouse_uuid' => $this->warehouse->uuid,
            'sale_date' => now()->toDateString(),
            'items' => [[
                'product_uuid' => $this->serviceProduct->uuid,
                'quantity' => 1,
                'unit_price' => 100,
            ]],
        ]);

        $sale = app(SaleService::class)->confirm($sale->uuid);

        $this->assertEquals(100, (float) $sale->due_amount);
        $this->assertEquals(0, (float) $sale->paid_amount);

        $payment = app(PaymentServiceInterface::class)->create([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'payment_no' => 'REC-SALE-REV-' . uniqid(),
            'payment_type' => PaymentTypeEnum::RECEIPT->value,
            'purpose' => PaymentPurposeEnum::CUSTOMER_PAYMENT->value,
            'payment_date' => now()->toDateString(),
            'party_type' => PaymentPartyTypeEnum::CUSTOMER->value,
            'party_id' => $this->customer->id,
            'account_id' => $this->cashAccount->id,
            'amount' => 40,
            'payment_method' => PaymentMethodEnum::CASH->value,
            'allocations' => [[
                'document_type' => 'sales_invoice',
                'document_id' => $sale->id,
                'allocated_amount' => 40,
            ]],
        ]);

        $payment = app(PaymentServiceInterface::class)->confirm($payment);

        $sale->refresh();

        $this->assertEquals(40, (float) $sale->paid_amount);
        $this->assertEquals(60, (float) $sale->due_amount);

        SalesReturn::create([
            'sale_id' => $sale->id,
            'customer_id' => $this->customer->id,
            'return_no' => 'SR-PAY-REV-001',
            'return_date' => now()->toDateString(),
            'subtotal' => 20,
            'discount_amount' => 0,
            'tax_amount' => 0,
            'rounding_amount' => 0,
            'grand_total' => 20,
            'refund_amount' => 0,
            'credited_amount' => 20,
            'refund_type' => SalesReturnRefundType::CREDIT_NOTE,
            'status' => SalesReturnStatus::CONFIRMED,
        ]);

        $sale->update(['due_amount' => 40]);

        app(PaymentServiceInterface::class)->reverse($payment);

        $sale->refresh();

        $this->assertEquals(0, (float) $sale->paid_amount);
        $this->assertEquals(80, (float) $sale->due_amount);
        $this->assertSame(
            DocumentStatusEnum::REVERSED->value,
            $payment->fresh()->status
        );
    }

    public function test_purchase_bill_paid_and_due_balances_are_restored_when_supplier_payment_is_reversed(): void
    {
        $purchase = app(PurchaseService::class)->create([
            'supplier_uuid' => $this->supplier->uuid,
            'warehouse_uuid' => $this->warehouse->uuid,
            'purchase_date' => now()->toDateString(),
            'items' => [[
                'product_uuid' => $this->serviceProduct->uuid,
                'quantity' => 1,
                'unit_cost' => 100,
            ]],
        ]);

        $purchase = app(PurchaseService::class)->confirm($purchase);

        $this->assertEquals(100, (float) $purchase->due_amount);
        $this->assertEquals(0, (float) $purchase->paid_amount);

        $payment = app(PaymentServiceInterface::class)->create([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'payment_no' => 'PAY-PURCHASE-REV-' . uniqid(),
            'payment_type' => PaymentTypeEnum::PAYMENT->value,
            'purpose' => PaymentPurposeEnum::SUPPLIER_PAYMENT->value,
            'payment_date' => now()->toDateString(),
            'party_type' => PaymentPartyTypeEnum::SUPPLIER->value,
            'party_id' => $this->supplier->id,
            'account_id' => $this->cashAccount->id,
            'amount' => 40,
            'payment_method' => PaymentMethodEnum::CASH->value,
            'allocations' => [[
                'document_type' => 'purchase_bill',
                'document_id' => $purchase->id,
                'allocated_amount' => 40,
            ]],
        ]);

        $payment = app(PaymentServiceInterface::class)->confirm($payment);

        $purchase->refresh();

        $this->assertEquals(40, (float) $purchase->paid_amount);
        $this->assertEquals(60, (float) $purchase->due_amount);

        PurchaseReturn::create([
            'purchase_id' => $purchase->id,
            'supplier_id' => $this->supplier->id,
            'return_no' => 'PR-PAY-REV-001',
            'return_date' => now()->toDateString(),
            'subtotal' => 20,
            'discount_amount' => 0,
            'tax_amount' => 0,
            'rounding_amount' => 0,
            'grand_total' => 20,
            'refund_amount' => 0,
            'credited_amount' => 20,
            'refund_type' => PurchaseReturnRefundType::CREDIT_NOTE,
            'status' => PurchaseReturnStatus::CONFIRMED,
        ]);

        $purchase->update(['due_amount' => 40]);

        app(PaymentServiceInterface::class)->reverse($payment);

        $purchase->refresh();

        $this->assertEquals(0, (float) $purchase->paid_amount);
        $this->assertEquals(80, (float) $purchase->due_amount);
        $this->assertSame(
            DocumentStatusEnum::REVERSED->value,
            $payment->fresh()->status
        );
    }

}
