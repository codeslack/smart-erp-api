<?php

namespace Tests\Feature\Payment;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

use Tests\Support\CreatesTenant;
use Tests\Support\CreatesSupplier;
use Tests\Support\CreatesCustomer;

use App\Core\Enums\DocumentStatusEnum;

use App\Modules\Customer\Models\Customer;
use App\Modules\Supplier\Models\Supplier;

use App\Modules\Tenant\Models\Tenant;
use App\Modules\Payment\Models\Payment;
use App\Modules\Payment\Enums\PaymentTypeEnum;
use App\Modules\Payment\Enums\PaymentPurposeEnum;
use App\Modules\Payment\Enums\PaymentMethodEnum;
use App\Modules\Payment\Enums\PaymentPartyTypeEnum;

use App\Modules\Accounting\Enums\AccountingAccounts;
use App\Modules\Accounting\Models\ChartOfAccount;
use App\Modules\Accounting\Models\JournalEntry;
use App\Modules\Accounting\Services\AccountingSetupService;

use App\Modules\Payment\Services\Contracts\PaymentPostingServiceInterface;

class PaymentConfirmationTest extends TestCase
{
    use RefreshDatabase;
    use CreatesTenant;
    use CreatesCustomer;
    use CreatesSupplier;

    protected Tenant $tenant;
    protected ChartOfAccount $cashAccount;

    protected Customer $customer;
    protected Supplier $supplier;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = $this->createTestTenant();

        app(AccountingSetupService::class)
            ->setup($this->tenant);

        $this->cashAccount = ChartOfAccount::query()
            ->where('tenant_id', $this->tenant->id)
            ->where(
                'account_code',
                AccountingAccounts::CASH
            )
            ->firstOrFail();

        $this->customer = $this->createCustomer();

        $this->supplier = $this->createSupplier();
    }

    public function test_receipt_confirmation_posts_accounting_and_confirms_payment(): void
    {
        $payment = Payment::create([
            'payment_no' => 'REC-0001',
            'payment_type' => PaymentTypeEnum::RECEIPT,
            'purpose' => PaymentPurposeEnum::CUSTOMER_PAYMENT,
            'payment_date' => now()->toDateString(),
            'party_type' => PaymentPartyTypeEnum::CUSTOMER,
            'party_id' => $this->customer->id,
            'account_id' => $this->cashAccount->id,
            'amount' => '1000.0000',
            'payment_method' => PaymentMethodEnum::CASH,
            'status' => DocumentStatusEnum::DRAFT->value,
        ]);

        $confirmed = app(
            PaymentPostingServiceInterface::class
        )->confirm($payment);

        $this->assertSame(
            DocumentStatusEnum::CONFIRMED->value,
            $confirmed->status
        );

        $this->assertDatabaseHas('payments', [
            'id' => $payment->id,
            'tenant_id' => $this->tenant->id,
            'status' => DocumentStatusEnum::CONFIRMED->value,
        ]);

        $this->assertDatabaseHas('journal_entries', [
            'tenant_id' => $this->tenant->id,
            'reference_type' => Payment::class,
            'reference_id' => $payment->id,
        ]);
    }

    public function test_unallocated_receipt_debits_cash_and_credits_customer_advances(): void
    {
        $payment = Payment::create([
            'payment_no' => 'REC-0002',
            'payment_type' => PaymentTypeEnum::RECEIPT,
            'purpose' => PaymentPurposeEnum::CUSTOMER_PAYMENT,
            'payment_date' => now()->toDateString(),
            'party_type' => PaymentPartyTypeEnum::CUSTOMER,
            'party_id' => $this->customer->id,
            'account_id' => $this->cashAccount->id,
            'amount' => '1000.0000',
            'payment_method' => PaymentMethodEnum::CASH,
            'status' => DocumentStatusEnum::DRAFT->value,
        ]);

        $confirmed = app(
            PaymentPostingServiceInterface::class
        )->confirm($payment);

        $this->assertSame(
            DocumentStatusEnum::CONFIRMED->value,
            $confirmed->status
        );

        $journalEntry = JournalEntry::query()
            ->where('tenant_id', $this->tenant->id)
            ->where('reference_type', Payment::class)
            ->where('reference_id', $payment->id)
            ->firstOrFail();

        $journalEntry->load('lines.account');

        $cashLine = $journalEntry->lines
            ->firstWhere(
                'chart_of_account_id',
                $this->cashAccount->id
            );

        $advanceAccount = ChartOfAccount::query()
            ->where('tenant_id', $this->tenant->id)
            ->where(
                'account_code',
                AccountingAccounts::CUSTOMER_ADVANCES
            )
            ->firstOrFail();

        $advanceLine = $journalEntry->lines
            ->firstWhere(
                'chart_of_account_id',
                $advanceAccount->id
            );

        $this->assertNotNull($cashLine);
        $this->assertNotNull($advanceLine);

        $this->assertSame(
            '1000.0000',
            (string) $cashLine->debit
        );

        $this->assertSame(
            '0.0000',
            (string) $cashLine->credit
        );

        $this->assertSame(
            '0.0000',
            (string) $advanceLine->debit
        );

        $this->assertSame(
            '1000.0000',
            (string) $advanceLine->credit
        );
    }

    public function test_partially_allocated_receipt_credits_receivable_and_remaining_customer_advance(): void
    {
        $payment = Payment::create([
            'payment_no' => 'REC-0003',
            'payment_type' => PaymentTypeEnum::RECEIPT,
            'purpose' => PaymentPurposeEnum::CUSTOMER_PAYMENT,
            'payment_date' => now()->toDateString(),
            'party_type' => PaymentPartyTypeEnum::CUSTOMER,
            'party_id' => $this->customer->id,
            'account_id' => $this->cashAccount->id,
            'amount' => '1000.0000',
            'payment_method' => PaymentMethodEnum::CASH,
            'status' => DocumentStatusEnum::DRAFT->value,
        ]);

        $payment->allocations()->create([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'document_type' => 'sales_invoice',
            'document_id' => 1001,
            'allocated_amount' => '700.0000',
        ]);

        $confirmed = app(
            PaymentPostingServiceInterface::class
        )->confirm($payment);

        $this->assertSame(
            DocumentStatusEnum::CONFIRMED->value,
            $confirmed->status
        );

        $journalEntry = JournalEntry::query()
            ->where('tenant_id', $this->tenant->id)
            ->where('reference_type', Payment::class)
            ->where('reference_id', $payment->id)
            ->firstOrFail();

        $journalEntry->load('lines.account');

        $cashLine = $journalEntry->lines
            ->firstWhere(
                'chart_of_account_id',
                $this->cashAccount->id
            );

        $receivableAccount = ChartOfAccount::query()
            ->where('tenant_id', $this->tenant->id)
            ->where(
                'account_code',
                AccountingAccounts::ACCOUNTS_RECEIVABLE
            )
            ->firstOrFail();

        $advanceAccount = ChartOfAccount::query()
            ->where('tenant_id', $this->tenant->id)
            ->where(
                'account_code',
                AccountingAccounts::CUSTOMER_ADVANCES
            )
            ->firstOrFail();

        $receivableLine = $journalEntry->lines
            ->firstWhere(
                'chart_of_account_id',
                $receivableAccount->id
            );

        $advanceLine = $journalEntry->lines
            ->firstWhere(
                'chart_of_account_id',
                $advanceAccount->id
            );

        $this->assertNotNull($cashLine);
        $this->assertNotNull($receivableLine);
        $this->assertNotNull($advanceLine);

        $this->assertSame(
            '1000.0000',
            (string) $cashLine->debit
        );

        $this->assertSame(
            '0.0000',
            (string) $cashLine->credit
        );

        $this->assertSame(
            '0.0000',
            (string) $receivableLine->debit
        );

        $this->assertSame(
            '700.0000',
            (string) $receivableLine->credit
        );

        $this->assertSame(
            '0.0000',
            (string) $advanceLine->debit
        );

        $this->assertSame(
            '300.0000',
            (string) $advanceLine->credit
        );
    }

    public function test_unallocated_payment_debits_supplier_advances_and_credits_cash(): void
    {
        $payment = Payment::create([
            'payment_no' => 'PAY-0001',
            'payment_type' => PaymentTypeEnum::PAYMENT,
            'purpose' => PaymentPurposeEnum::SUPPLIER_PAYMENT,
            'payment_date' => now()->toDateString(),
            'party_type' => PaymentPartyTypeEnum::SUPPLIER,
            'party_id' => $this->supplier->id,
            'account_id' => $this->cashAccount->id,
            'amount' => '1000.0000',
            'payment_method' => PaymentMethodEnum::CASH,
            'status' => DocumentStatusEnum::DRAFT->value,
        ]);

        $confirmed = app(
            PaymentPostingServiceInterface::class
        )->confirm($payment);

        $this->assertSame(
            DocumentStatusEnum::CONFIRMED->value,
            $confirmed->status
        );

        $journalEntry = JournalEntry::query()
            ->where('tenant_id', $this->tenant->id)
            ->where('reference_type', Payment::class)
            ->where('reference_id', $payment->id)
            ->firstOrFail();

        $journalEntry->load('lines.account');

        $cashLine = $journalEntry->lines
            ->firstWhere(
                'chart_of_account_id',
                $this->cashAccount->id
            );

        $advanceAccount = ChartOfAccount::query()
            ->where('tenant_id', $this->tenant->id)
            ->where(
                'account_code',
                AccountingAccounts::SUPPLIER_ADVANCES
            )
            ->firstOrFail();

        $advanceLine = $journalEntry->lines
            ->firstWhere(
                'chart_of_account_id',
                $advanceAccount->id
            );

        $this->assertNotNull($cashLine);
        $this->assertNotNull($advanceLine);

        $this->assertSame(
            '0.0000',
            (string) $cashLine->debit
        );

        $this->assertSame(
            '1000.0000',
            (string) $cashLine->credit
        );

        $this->assertSame(
            '1000.0000',
            (string) $advanceLine->debit
        );

        $this->assertSame(
            '0.0000',
            (string) $advanceLine->credit
        );
    }

    public function test_partially_allocated_payment_debits_payable_and_remaining_supplier_advance(): void
    {
        $payment = Payment::create([
            'payment_no' => 'PAY-0002',
            'payment_type' => PaymentTypeEnum::PAYMENT,
            'purpose' => PaymentPurposeEnum::SUPPLIER_PAYMENT,
            'payment_date' => now()->toDateString(),
            'party_type' => PaymentPartyTypeEnum::SUPPLIER,
            'party_id' => $this->supplier->id,
            'account_id' => $this->cashAccount->id,
            'amount' => '1000.0000',
            'payment_method' => PaymentMethodEnum::CASH,
            'status' => DocumentStatusEnum::DRAFT->value,
        ]);

        $payment->allocations()->create([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'document_type' => 'purchase_bill',
            'document_id' => 1001,
            'allocated_amount' => '700.0000',
        ]);

        $confirmed = app(
            PaymentPostingServiceInterface::class
        )->confirm($payment);

        $this->assertSame(
            DocumentStatusEnum::CONFIRMED->value,
            $confirmed->status
        );

        $journalEntry = JournalEntry::query()
            ->where('tenant_id', $this->tenant->id)
            ->where('reference_type', Payment::class)
            ->where('reference_id', $payment->id)
            ->firstOrFail();

        $journalEntry->load('lines.account');

        $cashLine = $journalEntry->lines
            ->firstWhere(
                'chart_of_account_id',
                $this->cashAccount->id
            );

        $payableAccount = ChartOfAccount::query()
            ->where('tenant_id', $this->tenant->id)
            ->where(
                'account_code',
                AccountingAccounts::ACCOUNTS_PAYABLE
            )
            ->firstOrFail();

        $advanceAccount = ChartOfAccount::query()
            ->where('tenant_id', $this->tenant->id)
            ->where(
                'account_code',
                AccountingAccounts::SUPPLIER_ADVANCES
            )
            ->firstOrFail();

        $payableLine = $journalEntry->lines
            ->firstWhere(
                'chart_of_account_id',
                $payableAccount->id
            );

        $advanceLine = $journalEntry->lines
            ->firstWhere(
                'chart_of_account_id',
                $advanceAccount->id
            );

        $this->assertNotNull($cashLine);
        $this->assertNotNull($payableLine);
        $this->assertNotNull($advanceLine);

        $this->assertSame(
            '0.0000',
            (string) $cashLine->debit
        );

        $this->assertSame(
            '1000.0000',
            (string) $cashLine->credit
        );

        $this->assertSame(
            '700.0000',
            (string) $payableLine->debit
        );

        $this->assertSame(
            '0.0000',
            (string) $payableLine->credit
        );

        $this->assertSame(
            '300.0000',
            (string) $advanceLine->debit
        );

        $this->assertSame(
            '0.0000',
            (string) $advanceLine->credit
        );
    }
}
