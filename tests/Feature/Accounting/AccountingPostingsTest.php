<?php

namespace Tests\Feature\Accounting;

use Tests\TestCase;
use Tests\Support\CreatesTenant;
use Illuminate\Support\Str;
use Illuminate\Foundation\Testing\RefreshDatabase;

use App\Core\Tenant\TenantManager;
use App\Modules\Tenant\Models\Tenant;
use App\Modules\User\Models\User;
use App\Modules\Customer\Models\Customer;
use App\Modules\Supplier\Models\Supplier;
use App\Modules\Sales\Models\Sale;
use App\Modules\Purchase\Models\Purchase;
use App\Modules\PurchaseReturn\Models\PurchaseReturn;
use App\Modules\PurchaseReturn\Enums\PurchaseReturnRefundType;
use App\Modules\Warehouse\Models\Warehouse;
use App\Modules\Accounting\Models\JournalEntry;
use App\Modules\SalesReturn\Models\SalesReturn;
use App\Modules\SalesReturn\Enums\SalesReturnStatus;
use App\Modules\Payment\Models\Payment;
use App\Modules\Payment\Enums\PaymentTypeEnum;
use App\Modules\Payment\Enums\PaymentPartyTypeEnum;
use App\Modules\Payment\Enums\PaymentMethodEnum;
use App\Modules\Payment\Enums\PaymentPurposeEnum;
use App\Modules\Accounting\Models\ChartOfAccount;
use App\Modules\Accounting\Enums\AccountingAccounts;
use App\Modules\Accounting\Enums\JournalEntryStatusEnum;
use App\Modules\Accounting\Services\AccountingSetupService;
use App\Modules\Accounting\Services\Postings\SalesPostingService;
use App\Modules\Accounting\Services\Postings\PurchasePostingService;
use App\Modules\Accounting\Services\Postings\PurchaseReturnPostingService;
use App\Modules\Accounting\Services\Postings\SalesReturnPostingService;
use App\Modules\Accounting\Services\Postings\PaymentPostingService;

class AccountingPostingsTest extends TestCase
{
    use RefreshDatabase;
    use CreatesTenant;

    protected Tenant $tenant;
    protected User $user;
    protected Customer $customer;
    protected Supplier $supplier;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = $this->createTestTenant();
        app(TenantManager::class)->setTenant($this->tenant);

        $this->user = User::query()->create([
            'uuid' => (string) Str::uuid(),
            'code' => 'TEST-USER',
            'tenant_id' => $this->tenant->id,
            'name' => 'Posting Admin',
            'email' => 'postings-' . uniqid() . '@example.com',
            'password' => 'password',
            'is_active' => true,
        ]);

        app(AccountingSetupService::class)->setup($this->tenant);

        $this->customer = Customer::query()->create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Test Customer',
            'code' => 'CUST-001',
            'is_active' => true,
        ]);

        $this->supplier = Supplier::query()->create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Test Supplier',
            'code' => 'SUP-001',
            'is_active' => true,
        ]);
    }

    public function test_sales_posting_creates_posted_journal_entry(): void
    {
        $sale = Sale::query()->create([
            'tenant_id' => $this->tenant->id,
            'warehouse_id' => $this->customer->id,
            'customer_id' => $this->customer->id,
            'sale_no' => 'SALE-101',
            'sale_date' => now()->toDateString(),
            'subtotal' => 1500,
            'grand_total' => 1500,
            'due_amount' => 1500,
            'paid_amount' => 0,
            'status' => 'confirmed',
        ]);

        app(SalesPostingService::class)->post($sale);

        $this->assertDatabaseHas('journal_entries', [
            'tenant_id' => $this->tenant->id,
            'reference_type' => Sale::class,
            'reference_id' => $sale->id,
            'status' => JournalEntryStatusEnum::POSTED->value,
        ]);
    }

    public function test_foreign_currency_purchase_posts_base_and_foreign_amounts(): void
    {
        $warehouse = Warehouse::query()->create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Accounting Posting Warehouse',
            'code' => 'WH-ACCOUNTING-POST',
            'is_active' => true,
        ]);

        $purchase = Purchase::query()->create([
            'tenant_id' => $this->tenant->id,
            'supplier_id' => $this->supplier->id,
            'warehouse_id' => $warehouse->id,
            'purchase_no' => 'PUR-USD-101',
            'purchase_date' => now()->toDateString(),
            'currency_code' => 'USD',
            'exchange_rate' => '83.250000',
            'subtotal' => '82.0000',
            'tax_amount' => '18.0000',
            'grand_total' => '100.0000',
            'paid_amount' => '0.0000',
            'due_amount' => '100.0000',
            'status' => 'confirmed',
        ]);

        app(PurchasePostingService::class)->post($purchase);

        $journal = JournalEntry::query()
            ->where('reference_type', Purchase::class)
            ->where('reference_id', $purchase->id)
            ->firstOrFail();

        $this->assertSame('USD', $journal->currency_code);
        $this->assertSame('83.25000000', $journal->exchange_rate);

        $lines = $journal->lines()
            ->with('account')
            ->get()
            ->keyBy(fn ($line) => $line->account->account_code);

        $this->assertEquals(
            6826.50,
            (float) $lines->get(AccountingAccounts::INVENTORY)->debit
        );
        $this->assertEquals(
            82.00,
            (float) $lines->get(AccountingAccounts::INVENTORY)->foreign_debit
        );

        $this->assertEquals(
            1498.50,
            (float) $lines->get(AccountingAccounts::INPUT_TAX_RECEIVABLE)->debit
        );
        $this->assertEquals(
            18.00,
            (float) $lines->get(AccountingAccounts::INPUT_TAX_RECEIVABLE)->foreign_debit
        );

        $this->assertEquals(
            8325.00,
            (float) $lines->get(AccountingAccounts::ACCOUNTS_PAYABLE)->credit
        );
        $this->assertEquals(
            100.00,
            (float) $lines->get(AccountingAccounts::ACCOUNTS_PAYABLE)->foreign_credit
        );

        $this->assertEquals(
            8325.00,
            (float) $journal->lines()->sum('debit')
        );
        $this->assertEquals(
            8325.00,
            (float) $journal->lines()->sum('credit')
        );

        $purchaseReturn = PurchaseReturn::query()->create([
            'tenant_id' => $this->tenant->id,
            'purchase_id' => $purchase->id,
            'supplier_id' => $this->supplier->id,
            'return_no' => 'PUR-RETURN-USD-101',
            'return_date' => now()->toDateString(),
            'subtotal' => '41.0000',
            'tax_amount' => '9.0000',
            'grand_total' => '50.0000',
            'refund_amount' => '0.0000',
            'credited_amount' => '50.0000',
            'refund_type' => PurchaseReturnRefundType::CREDIT_NOTE,
            'status' => 'confirmed',
        ]);

        app(PurchaseReturnPostingService::class)->post($purchaseReturn);

        $returnJournal = JournalEntry::query()
            ->where('reference_type', PurchaseReturn::class)
            ->where('reference_id', $purchaseReturn->id)
            ->firstOrFail();

        $this->assertSame('USD', $returnJournal->currency_code);
        $this->assertSame('83.25000000', $returnJournal->exchange_rate);

        $returnLines = $returnJournal->lines()
            ->with('account')
            ->get()
            ->keyBy(fn ($line) => $line->account->account_code);

        $this->assertEquals(
            4162.50,
            (float) $returnLines->get(AccountingAccounts::ACCOUNTS_PAYABLE)->debit
        );
        $this->assertEquals(
            50.00,
            (float) $returnLines->get(AccountingAccounts::ACCOUNTS_PAYABLE)->foreign_debit
        );
        $this->assertEquals(
            3413.25,
            (float) $returnLines->get(AccountingAccounts::INVENTORY)->credit
        );
        $this->assertEquals(
            41.00,
            (float) $returnLines->get(AccountingAccounts::INVENTORY)->foreign_credit
        );
        $this->assertEquals(
            749.25,
            (float) $returnLines->get(AccountingAccounts::INPUT_TAX_RECEIVABLE)->credit
        );
        $this->assertEquals(
            9.00,
            (float) $returnLines->get(AccountingAccounts::INPUT_TAX_RECEIVABLE)->foreign_credit
        );
    }

    public function test_sales_return_posting_creates_posted_journal_entry(): void
    {
        $sale = Sale::query()->create([
            'tenant_id' => $this->tenant->id,
            'warehouse_id' => $this->customer->id,
            'customer_id' => $this->customer->id,
            'sale_no' => 'SALE-102',
            'sale_date' => now()->toDateString(),
            'subtotal' => 2000,
            'grand_total' => 2000,
            'due_amount' => 2000,
            'paid_amount' => 0,
            'status' => 'confirmed',
        ]);

        $salesReturn = SalesReturn::query()->create([
            'tenant_id' => $this->tenant->id,
            'warehouse_id' => $this->customer->id,
            'customer_id' => $this->customer->id,
            'sale_id' => $sale->id,
            'return_no' => 'SR-101',
            'return_date' => now()->toDateString(),
            'grand_total' => 500,
            'status' => SalesReturnStatus::CONFIRMED->value,
        ]);

        app(SalesReturnPostingService::class)->post($salesReturn);

        $this->assertDatabaseHas('journal_entries', [
            'tenant_id' => $this->tenant->id,
            'reference_type' => SalesReturn::class,
            'reference_id' => $salesReturn->id,
            'status' => JournalEntryStatusEnum::POSTED->value,
        ]);
    }

    public function test_payment_posting_creates_posted_journal_entry(): void
    {
        $cashAccount = ChartOfAccount::query()
            ->where('account_code', AccountingAccounts::CASH)
            ->firstOrFail();

        $payment = Payment::query()->create([
            'tenant_id' => $this->tenant->id,
            'payment_no' => 'PAY-201',
            'payment_type' => PaymentTypeEnum::PAYMENT,
            'purpose' => PaymentPurposeEnum::SUPPLIER_PAYMENT,
            'party_type' => PaymentPartyTypeEnum::SUPPLIER,
            'party_id' => $this->supplier->id,
            'payment_date' => now()->toDateString(),
            'account_id' => $cashAccount->id,
            'amount' => 1200,
            'payment_method' => PaymentMethodEnum::CASH,
            'status' => 'confirmed',
        ]);

        app(PaymentPostingService::class)->post($payment);

        $this->assertDatabaseHas('journal_entries', [
            'tenant_id' => $this->tenant->id,
            'reference_type' => Payment::class,
            'reference_id' => $payment->id,
            'status' => JournalEntryStatusEnum::POSTED->value,
        ]);
    }

    public function test_customer_receipt_posting_creates_posted_journal_entry(): void
    {
        $cashAccount = ChartOfAccount::query()
            ->where('account_code', AccountingAccounts::CASH)
            ->firstOrFail();

        $payment = Payment::query()->create([
            'tenant_id' => $this->tenant->id,
            'payment_no' => 'PAY-202',
            'payment_type' => PaymentTypeEnum::RECEIPT,
            'purpose' => PaymentPurposeEnum::CUSTOMER_PAYMENT,
            'party_type' => PaymentPartyTypeEnum::CUSTOMER,
            'party_id' => $this->customer->id,
            'payment_date' => now()->toDateString(),
            'account_id' => $cashAccount->id,
            'amount' => 800,
            'payment_method' => PaymentMethodEnum::CASH,
            'status' => 'confirmed',
        ]);

        app(PaymentPostingService::class)->post($payment);

        $this->assertDatabaseHas('journal_entries', [
            'tenant_id' => $this->tenant->id,
            'reference_type' => Payment::class,
            'reference_id' => $payment->id,
            'status' => JournalEntryStatusEnum::POSTED->value,
        ]);
    }
}