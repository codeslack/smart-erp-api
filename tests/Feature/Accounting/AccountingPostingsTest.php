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

    public function test_repeated_sales_posting_does_not_duplicate_journal_or_ledger_entries(): void
    {
        $sale = Sale::query()->create([
            'tenant_id' => $this->tenant->id,
            'warehouse_id' => $this->customer->id,
            'customer_id' => $this->customer->id,
            'sale_no' => 'SALE-IDEMPOTENT-101',
            'sale_date' => now()->toDateString(),
            'subtotal' => 1500,
            'grand_total' => 1500,
            'due_amount' => 1500,
            'paid_amount' => 0,
            'status' => 'confirmed',
        ]);

        $service = app(SalesPostingService::class);

        $service->post($sale);
        $balancesAfterFirstPost = ChartOfAccount::query()
            ->where('tenant_id', $this->tenant->id)
            ->orderBy('id')
            ->pluck('current_balance')
            ->all();

        $service->post($sale->fresh());

        $this->assertSame(
            1,
            \Illuminate\Support\Facades\DB::table('journal_entries')
                ->where('tenant_id', $this->tenant->id)
                ->where('reference_type', Sale::class)
                ->where('reference_id', $sale->id)
                ->whereNull('reversal_of_journal_entry_id')
                ->count()
        );

        $this->assertSame(
            2,
            \Illuminate\Support\Facades\DB::table('account_ledgers')
                ->where('tenant_id', $this->tenant->id)
                ->whereIn(
                    'journal_entry_id',
                    \Illuminate\Support\Facades\DB::table('journal_entries')
                        ->select('id')
                        ->where('tenant_id', $this->tenant->id)
                        ->where('reference_type', Sale::class)
                        ->where('reference_id', $sale->id)
                )
                ->count()
        );

        $this->assertSame(
            $balancesAfterFirstPost,
            ChartOfAccount::query()
                ->where('tenant_id', $this->tenant->id)
                ->orderBy('id')
                ->pluck('current_balance')
                ->all()
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