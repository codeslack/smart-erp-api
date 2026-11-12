<?php

namespace Tests\Feature\Accounting;

use Tests\TestCase;
use Tests\Support\CreatesTenant;

use Illuminate\Foundation\Testing\RefreshDatabase;

use App\Core\Enums\OpeningBalanceTypeEnum;
use App\Core\Tenant\TenantManager;

use App\Modules\Tenant\Models\Tenant;
use App\Modules\Customer\Models\Customer;
use App\Modules\Supplier\Models\Supplier;
use App\Modules\CustomerOpeningBill\Models\CustomerOpeningBill;
use App\Modules\SupplierOpeningBill\Models\SupplierOpeningBill;

use App\Modules\Accounting\Enums\AccountingAccounts;
use App\Modules\Accounting\Enums\JournalEntryStatusEnum;
use App\Modules\Accounting\Enums\JournalVoucherTypeEnum;
use App\Modules\Accounting\Models\AccountLedger;
use App\Modules\Accounting\Models\ChartOfAccount;
use App\Modules\Accounting\Models\JournalEntry;
use App\Modules\Accounting\Services\AccountingSetupService;

use App\Modules\CustomerOpeningBill\Services\CustomerOpeningBillPostingService;
use App\Modules\SupplierOpeningBill\Services\SupplierOpeningBillPostingService;

class OpeningBalanceReconciliationTest extends TestCase
{
    use RefreshDatabase;
    use CreatesTenant;

    protected Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = $this->createTestTenant();

        app(TenantManager::class)->setTenant($this->tenant);

        app(AccountingSetupService::class)->setup($this->tenant);
    }

    public function test_customer_opening_bills_reconcile_to_receivables_advances_and_equity_after_repost(): void
    {
        $customer = Customer::query()->create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Opening Customer',
            'code' => 'CUS-OPEN-REC',
            'is_active' => true,
        ]);

        $receivableBill = CustomerOpeningBill::query()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $customer->id,
            'bill_no' => 'CUS-OB-001',
            'bill_date' => '2026-01-01',
            'due_date' => '2026-01-31',
            'amount' => 1000,
            'balance_amount' => 1000,
            'balance_type' => OpeningBalanceTypeEnum::DEBIT,
        ]);

        CustomerOpeningBill::query()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $customer->id,
            'bill_no' => 'CUS-OB-ADV-001',
            'bill_date' => '2026-01-01',
            'due_date' => '2026-01-31',
            'amount' => 200,
            'balance_amount' => 200,
            'balance_type' => OpeningBalanceTypeEnum::CREDIT,
        ]);

        $posting = app(CustomerOpeningBillPostingService::class);
        $posting->post($customer);

        $original = $this->openingJournal(
            Customer::class,
            $customer->id,
            JournalVoucherTypeEnum::CUSTOMER_OPENING_BALANCE->value
        );

        $this->assertEquals(
            1000,
            $this->accountBalance(AccountingAccounts::ACCOUNTS_RECEIVABLE)
        );
        $this->assertEquals(
            200,
            $this->accountBalance(AccountingAccounts::CUSTOMER_ADVANCES)
        );
        $this->assertEquals(
            800,
            $this->accountBalance(AccountingAccounts::OPENING_BALANCE_EQUITY)
        );

        $receivableBill->update([
            'amount' => 1500,
            'balance_amount' => 1500,
        ]);

        $posting->repost($customer);

        $this->assertSame(
            JournalEntryStatusEnum::REVERSED,
            $original->fresh()->status
        );

        $this->assertEquals(
            1500,
            $this->accountBalance(AccountingAccounts::ACCOUNTS_RECEIVABLE)
        );
        $this->assertEquals(
            200,
            $this->accountBalance(AccountingAccounts::CUSTOMER_ADVANCES)
        );
        $this->assertEquals(
            1300,
            $this->accountBalance(AccountingAccounts::OPENING_BALANCE_EQUITY)
        );

        $this->assertSame(
            3,
            AccountLedger::query()
                ->where('chart_of_account_id', $this->accountId(AccountingAccounts::ACCOUNTS_RECEIVABLE))
                ->count()
        );
    }

    public function test_supplier_opening_bills_reconcile_to_payables_advances_and_equity_after_repost(): void
    {
        $supplier = Supplier::query()->create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Opening Supplier',
            'code' => 'SUP-OPEN-REC',
            'is_active' => true,
        ]);

        $payableBill = SupplierOpeningBill::query()->create([
            'tenant_id' => $this->tenant->id,
            'supplier_id' => $supplier->id,
            'bill_no' => 'SUP-OB-001',
            'bill_date' => '2026-01-01',
            'due_date' => '2026-01-31',
            'amount' => 2000,
            'balance_amount' => 2000,
            'balance_type' => OpeningBalanceTypeEnum::CREDIT,
        ]);

        SupplierOpeningBill::query()->create([
            'tenant_id' => $this->tenant->id,
            'supplier_id' => $supplier->id,
            'bill_no' => 'SUP-OB-ADV-001',
            'bill_date' => '2026-01-01',
            'due_date' => '2026-01-31',
            'amount' => 300,
            'balance_amount' => 300,
            'balance_type' => OpeningBalanceTypeEnum::DEBIT,
        ]);

        $posting = app(SupplierOpeningBillPostingService::class);
        $posting->post($supplier);

        $original = $this->openingJournal(
            Supplier::class,
            $supplier->id,
            JournalVoucherTypeEnum::SUPPLIER_OPENING_BALANCE->value
        );

        $this->assertEquals(
            2000,
            $this->accountBalance(AccountingAccounts::ACCOUNTS_PAYABLE)
        );
        $this->assertEquals(
            300,
            $this->accountBalance(AccountingAccounts::SUPPLIER_ADVANCES)
        );
        $this->assertEquals(
            -1700,
            $this->accountBalance(AccountingAccounts::OPENING_BALANCE_EQUITY)
        );

        $payableBill->update([
            'amount' => 2500,
            'balance_amount' => 2500,
        ]);

        $posting->repost($supplier);

        $this->assertSame(
            JournalEntryStatusEnum::REVERSED,
            $original->fresh()->status
        );

        $this->assertEquals(
            2500,
            $this->accountBalance(AccountingAccounts::ACCOUNTS_PAYABLE)
        );
        $this->assertEquals(
            300,
            $this->accountBalance(AccountingAccounts::SUPPLIER_ADVANCES)
        );
        $this->assertEquals(
            -2200,
            $this->accountBalance(AccountingAccounts::OPENING_BALANCE_EQUITY)
        );
    }

    protected function openingJournal(
        string $referenceType,
        int $referenceId,
        string $voucherType
    ): JournalEntry {
        return JournalEntry::query()
            ->where('reference_type', $referenceType)
            ->where('reference_id', $referenceId)
            ->where('voucher_type', $voucherType)
            ->where('status', JournalEntryStatusEnum::POSTED->value)
            ->firstOrFail();
    }

    protected function accountId(string $accountCode): int
    {
        return (int) ChartOfAccount::query()
            ->where('account_code', $accountCode)
            ->value('id');
    }

    protected function accountBalance(string $accountCode): float
    {
        return (float) ChartOfAccount::query()
            ->where('account_code', $accountCode)
            ->value('current_balance');
    }
}
