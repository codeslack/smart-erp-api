<?php

namespace Tests\Feature\Accounting;

use App\Core\Enums\OpeningBalanceTypeEnum;
use App\Core\Tenant\TenantManager;
use App\Modules\Accounting\Enums\AccountingAccounts;
use App\Modules\Accounting\Models\ChartOfAccount;
use App\Modules\Accounting\Repositories\SupplierStatementRepository;
use App\Modules\Accounting\Services\AccountingSetupService;
use App\Modules\Customer\Models\Customer;
use App\Modules\CustomerOpeningBill\Models\CustomerOpeningBill;
use App\Modules\CustomerOpeningBill\Services\CustomerOpeningBillPostingService;
use App\Modules\Payment\Enums\PaymentMethodEnum;
use App\Modules\Payment\Enums\PaymentPartyTypeEnum;
use App\Modules\Payment\Enums\PaymentPurposeEnum;
use App\Modules\Payment\Enums\PaymentStatusEnum;
use App\Modules\Payment\Enums\PaymentTypeEnum;
use App\Modules\Payment\Models\Payment;
use App\Modules\Payment\Services\PaymentService;
use App\Modules\Purchase\Models\Purchase;
use App\Modules\PurchaseReturn\Enums\PurchaseReturnRefundType;
use App\Modules\PurchaseReturn\Enums\PurchaseReturnStatus;
use App\Modules\PurchaseReturn\Models\PurchaseReturn;
use App\Modules\Sales\Models\Sale;
use App\Modules\SalesReturn\Enums\SalesReturnRefundType;
use App\Modules\SalesReturn\Enums\SalesReturnStatus;
use App\Modules\SalesReturn\Models\SalesReturn;
use App\Modules\Supplier\Models\Supplier;
use App\Modules\SupplierOpeningBill\Models\SupplierOpeningBill;
use App\Modules\SupplierOpeningBill\Services\SupplierOpeningBillPostingService;
use App\Modules\Tenant\Models\Tenant;
use App\Modules\User\Models\User;
use App\Modules\Warehouse\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Support\CreatesCustomer;
use Tests\Support\CreatesSupplier;
use Tests\Support\CreatesTenant;
use Tests\Support\CreatesUser;
use Tests\Support\CreatesWarehouse;
use Tests\TestCase;

class AccountingStatementReconciliationTest extends TestCase
{
    use RefreshDatabase;
    use CreatesTenant;
    use CreatesUser;
    use CreatesCustomer;
    use CreatesSupplier;
    use CreatesWarehouse;

    protected Tenant $tenant;
    protected User $user;
    protected Customer $customer;
    protected Supplier $supplier;
    protected Warehouse $warehouse;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = $this->createTestTenant();
        app(TenantManager::class)->setTenant($this->tenant);

        $this->user = $this->createTestUser();
        Sanctum::actingAs($this->user);
        app(AccountingSetupService::class)->setup($this->tenant);

        $this->customer = $this->createCustomer();
        $this->supplier = $this->createSupplier();
        $this->warehouse = $this->createWarehouse();
    }

    public function test_customer_opening_bill_allocation_and_reversal_reconcile_to_receivable_control(): void
    {
        $openingBill = CustomerOpeningBill::query()->create([
            'customer_id' => $this->customer->id,
            'bill_no' => 'COB-RECON-001',
            'bill_date' => now()->toDateString(),
            'amount' => 5000,
            'balance_amount' => 5000,
            'balance_type' => OpeningBalanceTypeEnum::DEBIT,
        ]);

        app(CustomerOpeningBillPostingService::class)->post($this->customer);

        $payment = app(PaymentService::class)->create([
            'payment_no' => 'REC-RECON-001',
            'payment_type' => PaymentTypeEnum::RECEIPT,
            'purpose' => PaymentPurposeEnum::CUSTOMER_PAYMENT,
            'party_type' => PaymentPartyTypeEnum::CUSTOMER,
            'party_id' => $this->customer->id,
            'payment_date' => now()->toDateString(),
            'account_id' => $this->cashAccount()->id,
            'amount' => 2000,
            'payment_method' => PaymentMethodEnum::CASH,
            'allocations' => [[
                'document_type' => 'customer_opening_bill',
                'document_id' => $openingBill->id,
                'allocated_amount' => 2000,
            ]],
        ]);

        $payment = app(PaymentService::class)->confirm($payment);

        $response = $this->getJson(
            "/api/accounting/customer-statements/{$this->customer->uuid}"
        );

        // API middleware clears the tenant context; restore it for subsequent model queries.
        app(TenantManager::class)->setTenant($this->tenant);

        $response->assertSuccessful()
            ->assertJsonPath('data.base_currency_code', 'INR')
            ->assertJsonPath('data.opening_balance', 5000)
            ->assertJsonPath('data.outstanding_balance', 3000);

        $receivable = $this->account(AccountingAccounts::ACCOUNTS_RECEIVABLE);
        $this->assertEquals(3000, (float) $receivable->current_balance);

        app(PaymentService::class)->reverse($payment);

        $response = $this->getJson(
            "/api/accounting/customer-statements/{$this->customer->uuid}"
        );

        // API middleware clears the tenant context; restore it for subsequent model queries.
        app(TenantManager::class)->setTenant($this->tenant);

        $response->assertSuccessful()
            ->assertJsonPath('data.opening_balance', 5000)
            ->assertJsonPath('data.outstanding_balance', 5000);

        $this->assertEquals(
            5000,
            (float) $this->account(AccountingAccounts::ACCOUNTS_RECEIVABLE)->current_balance
        );
        $this->assertEquals(5000, (float) $openingBill->fresh()->balance_amount);
    }

    public function test_supplier_opening_bill_reconciles_to_payable_control(): void
    {
        $openingBill = SupplierOpeningBill::query()->create([
            'supplier_id' => $this->supplier->id,
            'bill_no' => 'SOB-RECON-001',
            'bill_date' => now()->toDateString(),
            'amount' => 5000,
            'balance_amount' => 5000,
            'balance_type' => OpeningBalanceTypeEnum::CREDIT,
        ]);

        app(SupplierOpeningBillPostingService::class)->post($this->supplier);

        $statementRepository = new SupplierStatementRepository();
        $statement = $statementRepository->getStatement(
            $this->supplier->id,
            now()->toDateString(),
            now()->toDateString(),
        );

        $this->assertEquals(0, $statement['opening_balance']);
        $this->assertEquals(5000, $statement['outstanding_balance']);
        $this->assertSame('INR', $statement['base_currency_code']);
        $this->assertCount(1, $statement['transactions']);

        $payable = $this->account(AccountingAccounts::ACCOUNTS_PAYABLE);

        $this->assertEquals(5000, (float) $payable->current_balance);
        $this->assertEquals(5000, (float) $openingBill->fresh()->balance_amount);
    }

    public function test_statements_use_base_currency_ignore_drafts_and_sign_refunds_correctly(): void
    {
        Sale::query()->create([
            'customer_id' => $this->customer->id,
            'warehouse_id' => $this->warehouse->id,
            'sale_no' => 'INV-FX-001',
            'sale_date' => now()->toDateString(),
            'currency_code' => 'USD',
            'exchange_rate' => 80,
            'subtotal' => 100,
            'grand_total' => 100,
            'due_amount' => 100,
            'paid_amount' => 0,
            'status' => 'confirmed',
        ]);

        Sale::query()->create([
            'customer_id' => $this->customer->id,
            'warehouse_id' => $this->warehouse->id,
            'sale_no' => 'INV-FX-DRAFT',
            'sale_date' => now()->toDateString(),
            'currency_code' => 'USD',
            'exchange_rate' => 80,
            'subtotal' => 1000,
            'grand_total' => 1000,
            'due_amount' => 1000,
            'paid_amount' => 0,
            'status' => 'draft',
        ]);

        $sale = Sale::query()->where('sale_no', 'INV-FX-001')->firstOrFail();

        SalesReturn::query()->create([
            'sale_id' => $sale->id,
            'customer_id' => $this->customer->id,
            'warehouse_id' => $this->warehouse->id,
            'return_no' => 'SR-FX-001',
            'return_date' => now()->toDateString(),
            'currency_code' => 'USD',
            'exchange_rate' => 80,
            'subtotal' => 10,
            'tax_amount' => 0,
            'grand_total' => 10,
            'refund_type' => SalesReturnRefundType::CREDIT_NOTE->value,
            'status' => SalesReturnStatus::CONFIRMED->value,
        ]);

        Purchase::query()->create([
            'supplier_id' => $this->supplier->id,
            'warehouse_id' => $this->warehouse->id,
            'purchase_no' => 'PUR-FX-001',
            'purchase_date' => now()->toDateString(),
            'currency_code' => 'USD',
            'exchange_rate' => 80,
            'subtotal' => 100,
            'grand_total' => 100,
            'due_amount' => 100,
            'paid_amount' => 0,
            'status' => 'confirmed',
        ]);

        Purchase::query()->create([
            'supplier_id' => $this->supplier->id,
            'warehouse_id' => $this->warehouse->id,
            'purchase_no' => 'PUR-FX-DRAFT',
            'purchase_date' => now()->toDateString(),
            'currency_code' => 'USD',
            'exchange_rate' => 80,
            'subtotal' => 1000,
            'grand_total' => 1000,
            'due_amount' => 1000,
            'paid_amount' => 0,
            'status' => 'draft',
        ]);

        $purchase = Purchase::query()->where('purchase_no', 'PUR-FX-001')->firstOrFail();

        PurchaseReturn::query()->create([
            'purchase_id' => $purchase->id,
            'supplier_id' => $this->supplier->id,
            'return_no' => 'PR-FX-001',
            'return_date' => now()->toDateString(),
            'subtotal' => 10,
            'tax_amount' => 0,
            'grand_total' => 10,
            'refund_type' => PurchaseReturnRefundType::CREDIT_NOTE->value,
            'status' => PurchaseReturnStatus::CONFIRMED->value,
        ]);

        Payment::query()->create([
            'payment_no' => 'REC-FX-ADV',
            'payment_type' => PaymentTypeEnum::RECEIPT,
            'purpose' => PaymentPurposeEnum::CUSTOMER_ADVANCE,
            'party_type' => PaymentPartyTypeEnum::CUSTOMER,
            'party_id' => $this->customer->id,
            'payment_date' => now()->toDateString(),
            'account_id' => $this->cashAccount()->id,
            'amount' => 100,
            'payment_method' => PaymentMethodEnum::CASH,
            'status' => PaymentStatusEnum::CONFIRMED->value,
        ]);

        Payment::query()->create([
            'payment_no' => 'REC-FX-REFUND',
            'payment_type' => PaymentTypeEnum::PAYMENT,
            'purpose' => PaymentPurposeEnum::CUSTOMER_REFUND,
            'party_type' => PaymentPartyTypeEnum::CUSTOMER,
            'party_id' => $this->customer->id,
            'payment_date' => now()->toDateString(),
            'account_id' => $this->cashAccount()->id,
            'amount' => 5,
            'payment_method' => PaymentMethodEnum::CASH,
            'status' => PaymentStatusEnum::CONFIRMED->value,
        ]);

        Payment::query()->create([
            'payment_no' => 'PAY-FX-ADV',
            'payment_type' => PaymentTypeEnum::PAYMENT,
            'purpose' => PaymentPurposeEnum::SUPPLIER_ADVANCE,
            'party_type' => PaymentPartyTypeEnum::SUPPLIER,
            'party_id' => $this->supplier->id,
            'payment_date' => now()->toDateString(),
            'account_id' => $this->cashAccount()->id,
            'amount' => 100,
            'payment_method' => PaymentMethodEnum::CASH,
            'status' => PaymentStatusEnum::CONFIRMED->value,
        ]);

        Payment::query()->create([
            'payment_no' => 'PAY-FX-REFUND',
            'payment_type' => PaymentTypeEnum::RECEIPT,
            'purpose' => PaymentPurposeEnum::SUPPLIER_REFUND,
            'party_type' => PaymentPartyTypeEnum::SUPPLIER,
            'party_id' => $this->supplier->id,
            'payment_date' => now()->toDateString(),
            'account_id' => $this->cashAccount()->id,
            'amount' => 5,
            'payment_method' => PaymentMethodEnum::CASH,
            'status' => PaymentStatusEnum::CONFIRMED->value,
        ]);

        $customerResponse = $this->getJson(
            "/api/accounting/customer-statements/{$this->customer->uuid}"
        );

        // API middleware clears the tenant context; restore it for subsequent model queries.
        app(TenantManager::class)->setTenant($this->tenant);

        $customerResponse->assertSuccessful()
            ->assertJsonPath('data.base_currency_code', 'INR')
            ->assertJsonPath('data.outstanding_balance', 7105);

        $customerRows = collect($customerResponse->json('data.transactions'));
        $this->assertSame(4, $customerRows->count());
        $this->assertEquals(
            8000,
            (float) $customerRows->firstWhere('reference', 'INV-FX-001')['base_amount']
        );

        $supplierResponse = $this->getJson(
            "/api/accounting/supplier-statements/{$this->supplier->uuid}"
        );

        // API middleware clears the tenant context; restore it for subsequent model queries.
        app(TenantManager::class)->setTenant($this->tenant);

        $supplierResponse->assertSuccessful()
            ->assertJsonPath('data.base_currency_code', 'INR')
            ->assertJsonPath('data.outstanding_balance', 7105);

        $supplierRows = collect($supplierResponse->json('data.transactions'));
        $this->assertSame(4, $supplierRows->count());
        $this->assertEquals(
            8000,
            (float) $supplierRows->firstWhere('reference', 'PUR-FX-001')['base_amount']
        );
    }

    protected function cashAccount(): ChartOfAccount
    {
        return $this->account(AccountingAccounts::CASH);
    }

    protected function account(string $code): ChartOfAccount
    {
        return ChartOfAccount::query()
            ->where('account_code', $code)
            ->firstOrFail();
    }
}
