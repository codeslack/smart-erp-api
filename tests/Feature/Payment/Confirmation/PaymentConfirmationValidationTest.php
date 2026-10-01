<?php

namespace Tests\Feature\Payment\Confirmation;

use Tests\TestCase;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use Illuminate\Foundation\Testing\RefreshDatabase;

use Tests\Support\CreatesTenant;
use Tests\Support\CreatesSupplier;
use Tests\Support\CreatesCustomer;

use App\Core\Enums\DocumentStatusEnum;
use App\Core\Exceptions\BusinessException;
use App\Core\Tenant\TenantManager;

use App\Modules\Tenant\Models\Tenant;
use App\Modules\Payment\Models\Payment;
use App\Modules\Payment\Models\PaymentAllocation;
use App\Modules\Payment\Enums\PaymentTypeEnum;
use App\Modules\Payment\Enums\PaymentMethodEnum;
use App\Modules\Payment\Enums\PaymentPartyTypeEnum;

use App\Modules\Customer\Models\Customer;
use App\Modules\Supplier\Models\Supplier;

use App\Core\Enums\OpeningBalanceTypeEnum;
use App\Modules\CustomerOpeningBill\Models\CustomerOpeningBill;

use App\Modules\Accounting\Enums\AccountingAccounts;
use App\Modules\Accounting\Models\ChartOfAccount;
use App\Modules\Accounting\Models\JournalEntry;
use App\Modules\Accounting\Services\AccountingSetupService;
use App\Modules\Payment\Services\Contracts\PaymentPostingServiceInterface;
use App\Modules\SupplierOpeningBill\Models\SupplierOpeningBill;

class PaymentConfirmationValidationTest extends TestCase
{
    use RefreshDatabase;
    use CreatesTenant;
    use CreatesSupplier;
    use CreatesCustomer;

    protected Tenant $tenant;
    protected ChartOfAccount $cashAccount;

    protected Customer $customer;
    protected Supplier $supplier;

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
            ->where('account_code', AccountingAccounts::CASH)
            ->firstOrFail();

        $this->customer = $this->createCustomer();

        $this->supplier = $this->createSupplier();
    }

    public function test_zero_allocation_is_rejected_and_payment_remains_draft(): void
    {
        $payment = Payment::create([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'payment_no' => 'RCT-VALIDATION-001',
            'payment_type' => PaymentTypeEnum::RECEIPT,
            'payment_date' => now()->toDateString(),
            'party_type' => PaymentPartyTypeEnum::CUSTOMER,
            'party_id' => $this->customer->id,
            'account_id' => $this->cashAccount->id,
            'amount' => 1000,
            'payment_method' => PaymentMethodEnum::CASH,
            'status' => DocumentStatusEnum::DRAFT->value,
        ]);

        $allocation = PaymentAllocation::create([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'payment_id' => $payment->id,
            'document_type' => 'sales_invoice',
            'document_id' => 1001,
            'allocated_amount' => 0,
        ]);

        $this->expectException(BusinessException::class);
        $this->expectExceptionMessage(
            'Amount must be greater than zero.'
        );

        app(PaymentPostingServiceInterface::class)
            ->confirm($payment);

        $payment->refresh();

        $this->assertSame(
            DocumentStatusEnum::DRAFT->value,
            $payment->status
        );

        $this->assertDatabaseHas('payment_allocations', [
            'id' => $allocation->id,
            'allocated_amount' => 0,
        ]);

        $this->assertDatabaseMissing('journal_entries', [
            'reference_type' => Payment::class,
            'reference_id' => $payment->id,
        ]);
    }

    public function test_negative_allocation_is_rejected_and_payment_remains_draft(): void
    {
        $payment = Payment::create([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'payment_no' => 'RCT-VALIDATION-002',
            'payment_type' => PaymentTypeEnum::RECEIPT,
            'payment_date' => now()->toDateString(),
            'party_type' => PaymentPartyTypeEnum::CUSTOMER,
            'party_id' => $this->customer->id,
            'account_id' => $this->cashAccount->id,
            'amount' => 1000,
            'payment_method' => PaymentMethodEnum::CASH,
            'status' => DocumentStatusEnum::DRAFT->value,
        ]);

        $allocation = PaymentAllocation::create([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'payment_id' => $payment->id,
            'document_type' => 'sales_invoice',
            'document_id' => 1002,
            'allocated_amount' => -100,
        ]);

        $this->expectException(BusinessException::class);
        $this->expectExceptionMessage(
            'Amount must be greater than zero.'
        );

        app(PaymentPostingServiceInterface::class)
            ->confirm($payment);

        $payment->refresh();

        $this->assertSame(
            DocumentStatusEnum::DRAFT->value,
            $payment->status
        );

        $this->assertDatabaseHas('payment_allocations', [
            'id' => $allocation->id,
            'allocated_amount' => -100,
        ]);

        $this->assertDatabaseMissing('journal_entries', [
            'reference_type' => Payment::class,
            'reference_id' => $payment->id,
        ]);
    }

    public function test_confirmed_payment_cannot_be_confirmed_again(): void
    {
        $payment = Payment::create([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'payment_no' => 'RCT-VALIDATION-003',
            'payment_type' => PaymentTypeEnum::RECEIPT,
            'payment_date' => now()->toDateString(),
            'party_type' => PaymentPartyTypeEnum::CUSTOMER,
            'party_id' => $this->customer->id,
            'account_id' => $this->cashAccount->id,
            'amount' => 1000,
            'payment_method' => PaymentMethodEnum::CASH,
            'status' => DocumentStatusEnum::CONFIRMED->value,
        ]);

        $this->expectException(BusinessException::class);
        $this->expectExceptionMessage(
            'Only draft payments can be confirmed.'
        );

        app(PaymentPostingServiceInterface::class)
            ->confirm($payment);

        $payment->refresh();

        $this->assertSame(
            DocumentStatusEnum::CONFIRMED->value,
            $payment->status
        );

        $this->assertDatabaseMissing('journal_entries', [
            'reference_type' => Payment::class,
            'reference_id' => $payment->id,
        ]);
    }

    public function test_receipt_with_supplier_party_is_rejected(): void
    {
        $payment = Payment::create([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'payment_no' => 'RCT-VALIDATION-004',
            'payment_type' => PaymentTypeEnum::RECEIPT,
            'payment_date' => now()->toDateString(),
            'party_type' => PaymentPartyTypeEnum::SUPPLIER,
            'party_id' => $this->supplier->id,
            'account_id' => $this->cashAccount->id,
            'amount' => 1000,
            'payment_method' => PaymentMethodEnum::CASH,
            'status' => DocumentStatusEnum::DRAFT->value,
        ]);

        $this->expectException(BusinessException::class);
        $this->expectExceptionMessage(
            'Receipt must belong to a customer.'
        );

        app(PaymentPostingServiceInterface::class)
            ->confirm($payment);

        $payment->refresh();

        $this->assertSame(
            DocumentStatusEnum::DRAFT->value,
            $payment->status
        );

        $this->assertDatabaseMissing('journal_entries', [
            'reference_type' => Payment::class,
            'reference_id' => $payment->id,
        ]);
    }

    public function test_receipt_with_nonexistent_customer_is_rejected(): void
    {
        $payment = Payment::create([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'payment_no' => 'RCT-VALIDATION-017',
            'payment_type' => PaymentTypeEnum::RECEIPT,
            'payment_date' => now()->toDateString(),
            'party_type' => PaymentPartyTypeEnum::CUSTOMER,
            'party_id' => 999999,
            'account_id' => $this->cashAccount->id,
            'amount' => 1000,
            'payment_method' => PaymentMethodEnum::CASH,
            'status' => DocumentStatusEnum::DRAFT->value,
        ]);

        try {
            app(PaymentPostingServiceInterface::class)
                ->confirm($payment);

            $this->fail(
                'Expected BusinessException was not thrown.'
            );
        } catch (BusinessException $exception) {
            $this->assertSame(
                'Customer not found.',
                $exception->getMessage()
            );
        }

        $payment->refresh();

        $this->assertSame(
            DocumentStatusEnum::DRAFT->value,
            $payment->status
        );

        $this->assertDatabaseMissing('journal_entries', [
            'reference_type' => Payment::class,
            'reference_id' => $payment->id,
        ]);
    }

    public function test_receipt_cannot_use_customer_from_another_tenant(): void
    {
        $otherTenant = $this->createTestTenant();

        app(TenantManager::class)
            ->setTenant($otherTenant);

        $otherCustomer = $this->createCustomer();

        app(TenantManager::class)
            ->setTenant($this->tenant);

        $payment = Payment::create([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'payment_no' => 'RCT-VALIDATION-019',
            'payment_type' => PaymentTypeEnum::RECEIPT,
            'payment_date' => now()->toDateString(),
            'party_type' => PaymentPartyTypeEnum::CUSTOMER,
            'party_id' => $otherCustomer->id,
            'account_id' => $this->cashAccount->id,
            'amount' => 1000,
            'payment_method' => PaymentMethodEnum::CASH,
            'status' => DocumentStatusEnum::DRAFT->value,
        ]);

        try {
            app(PaymentPostingServiceInterface::class)
                ->confirm($payment);

            $this->fail(
                'Expected BusinessException was not thrown.'
            );
        } catch (BusinessException $exception) {
            $this->assertSame(
                'Customer not found.',
                $exception->getMessage()
            );
        }

        $payment->refresh();

        $this->assertSame(
            DocumentStatusEnum::DRAFT->value,
            $payment->status
        );

        $this->assertDatabaseMissing('journal_entries', [
            'reference_type' => Payment::class,
            'reference_id' => $payment->id,
        ]);
    }

    public function test_payment_cannot_use_supplier_from_another_tenant(): void
    {
        $otherTenant = $this->createTestTenant();

        app(TenantManager::class)
            ->setTenant($otherTenant);

        $otherSupplier = $this->createSupplier();

        app(TenantManager::class)
            ->setTenant($this->tenant);

        $payment = Payment::create([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'payment_no' => 'PAY-VALIDATION-020',
            'payment_type' => PaymentTypeEnum::PAYMENT,
            'payment_date' => now()->toDateString(),
            'party_type' => PaymentPartyTypeEnum::SUPPLIER,
            'party_id' => $otherSupplier->id,
            'account_id' => $this->cashAccount->id,
            'amount' => 1000,
            'payment_method' => PaymentMethodEnum::CASH,
            'status' => DocumentStatusEnum::DRAFT->value,
        ]);

        try {
            app(PaymentPostingServiceInterface::class)
                ->confirm($payment);

            $this->fail(
                'Expected BusinessException was not thrown.'
            );
        } catch (BusinessException $exception) {
            $this->assertSame(
                'Supplier not found.',
                $exception->getMessage()
            );
        }

        $payment->refresh();

        $this->assertSame(
            DocumentStatusEnum::DRAFT->value,
            $payment->status
        );

        $this->assertDatabaseMissing('journal_entries', [
            'reference_type' => Payment::class,
            'reference_id' => $payment->id,
        ]);
    }

    public function test_payment_with_customer_party_is_rejected(): void
    {
        $payment = Payment::create([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'payment_no' => 'PAY-VALIDATION-005',
            'payment_type' => PaymentTypeEnum::PAYMENT,
            'payment_date' => now()->toDateString(),
            'party_type' => PaymentPartyTypeEnum::CUSTOMER,
            'party_id' => $this->customer->id,
            'account_id' => $this->cashAccount->id,
            'amount' => 1000,
            'payment_method' => PaymentMethodEnum::CASH,
            'status' => DocumentStatusEnum::DRAFT->value,
        ]);

        $this->expectException(BusinessException::class);
        $this->expectExceptionMessage(
            'Payment must belong to a supplier.'
        );

        app(PaymentPostingServiceInterface::class)
            ->confirm($payment);

        $payment->refresh();

        $this->assertSame(
            DocumentStatusEnum::DRAFT->value,
            $payment->status
        );

        $this->assertDatabaseMissing('journal_entries', [
            'reference_type' => Payment::class,
            'reference_id' => $payment->id,
        ]);
    }

    public function test_payment_with_nonexistent_supplier_is_rejected(): void
    {
        $payment = Payment::create([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'payment_no' => 'PAY-VALIDATION-018',
            'payment_type' => PaymentTypeEnum::PAYMENT,
            'payment_date' => now()->toDateString(),
            'party_type' => PaymentPartyTypeEnum::SUPPLIER,
            'party_id' => 999999,
            'account_id' => $this->cashAccount->id,
            'amount' => 1000,
            'payment_method' => PaymentMethodEnum::CASH,
            'status' => DocumentStatusEnum::DRAFT->value,
        ]);

        try {
            app(PaymentPostingServiceInterface::class)
                ->confirm($payment);

            $this->fail(
                'Expected BusinessException was not thrown.'
            );
        } catch (BusinessException $exception) {
            $this->assertSame(
                'Supplier not found.',
                $exception->getMessage()
            );
        }

        $payment->refresh();

        $this->assertSame(
            DocumentStatusEnum::DRAFT->value,
            $payment->status
        );

        $this->assertDatabaseMissing('journal_entries', [
            'reference_type' => Payment::class,
            'reference_id' => $payment->id,
        ]);
    }

    public function test_confirmation_fails_when_payment_account_does_not_exist(): void
    {
        $payment = Payment::create([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'payment_no' => 'RCT-VALIDATION-006',
            'payment_type' => PaymentTypeEnum::RECEIPT,
            'payment_date' => now()->toDateString(),
            'party_type' => PaymentPartyTypeEnum::CUSTOMER,
            'party_id' => $this->customer->id,
            'account_id' => 999999,
            'amount' => 1000,
            'payment_method' => PaymentMethodEnum::CASH,
            'status' => DocumentStatusEnum::DRAFT->value,
        ]);

        $this->expectException(BusinessException::class);
        $this->expectExceptionMessage(
            'Payment account not found.'
        );

        app(PaymentPostingServiceInterface::class)
            ->confirm($payment);

        $payment->refresh();

        $this->assertSame(
            DocumentStatusEnum::DRAFT->value,
            $payment->status
        );

        $this->assertDatabaseMissing('journal_entries', [
            'reference_type' => Payment::class,
            'reference_id' => $payment->id,
        ]);
    }

    public function test_confirmation_rejects_payment_account_from_another_tenant(): void
    {
        $otherTenant = $this->createTestTenant();

        app(TenantManager::class)
            ->setTenant($otherTenant);

        $accountGroupId = DB::table('account_groups')->insertGetId([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => $otherTenant->id,
            'name' => 'Test Cash Group',
            'code' => 'TEST-CASH',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $otherCashAccountId = DB::table('chart_of_accounts')->insertGetId([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => $otherTenant->id,
            'account_group_id' => $accountGroupId,
            'parent_id' => null,
            'account_code' => '9999',
            'account_name' => 'Other Tenant Cash',
            'account_type' => 'asset',
            'opening_balance' => 0,
            'current_balance' => 0,
            'is_system' => false,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        app(TenantManager::class)
            ->setTenant($this->tenant);

        $payment = Payment::create([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'payment_no' => 'RCT-VALIDATION-007',
            'payment_type' => PaymentTypeEnum::RECEIPT,
            'payment_date' => now()->toDateString(),
            'party_type' => PaymentPartyTypeEnum::CUSTOMER,
            'party_id' => $this->customer->id,
            'account_id' => $otherCashAccountId,
            'amount' => 1000,
            'payment_method' => PaymentMethodEnum::CASH,
            'status' => DocumentStatusEnum::DRAFT->value,
        ]);

        $this->expectException(BusinessException::class);
        $this->expectExceptionMessage(
            'Payment account not found.'
        );

        app(PaymentPostingServiceInterface::class)
            ->confirm($payment);

        $payment->refresh();

        $this->assertSame(
            DocumentStatusEnum::DRAFT->value,
            $payment->status
        );

        $this->assertDatabaseMissing('journal_entries', [
            'reference_type' => Payment::class,
            'reference_id' => $payment->id,
        ]);
    }

    public function test_confirmation_fails_when_payment_account_is_inactive(): void
    {
        $inactiveAccount = ChartOfAccount::query()
            ->where('tenant_id', $this->tenant->id)
            ->where('account_code', AccountingAccounts::CASH)
            ->firstOrFail();

        $inactiveAccount->update([
            'is_active' => false,
        ]);

        $payment = Payment::create([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'payment_no' => 'RCT-VALIDATION-008',
            'payment_type' => PaymentTypeEnum::RECEIPT,
            'payment_date' => now()->toDateString(),
            'party_type' => PaymentPartyTypeEnum::CUSTOMER,
            'party_id' => $this->customer->id,
            'account_id' => $inactiveAccount->id,
            'amount' => 1000,
            'payment_method' => PaymentMethodEnum::CASH,
            'status' => DocumentStatusEnum::DRAFT->value,
        ]);

        $this->expectException(BusinessException::class);
        $this->expectExceptionMessage(
            'Payment account not found.'
        );

        app(PaymentPostingServiceInterface::class)
            ->confirm($payment);

        $payment->refresh();

        $this->assertSame(
            DocumentStatusEnum::DRAFT->value,
            $payment->status
        );

        $this->assertDatabaseMissing('journal_entries', [
            'reference_type' => Payment::class,
            'reference_id' => $payment->id,
        ]);
    }

    public function test_zero_payment_amount_is_rejected(): void
    {
        $payment = Payment::create([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'payment_no' => 'RCT-VALIDATION-009',
            'payment_type' => PaymentTypeEnum::RECEIPT,
            'payment_date' => now()->toDateString(),
            'party_type' => PaymentPartyTypeEnum::CUSTOMER,
            'party_id' => $this->customer->id,
            'account_id' => $this->cashAccount->id,
            'amount' => 0,
            'payment_method' => PaymentMethodEnum::CASH,
            'status' => DocumentStatusEnum::DRAFT->value,
        ]);

        $this->expectException(BusinessException::class);
        $this->expectExceptionMessage(
            'Amount must be greater than zero.'
        );

        app(PaymentPostingServiceInterface::class)
            ->confirm($payment);

        $payment->refresh();

        $this->assertSame(
            DocumentStatusEnum::DRAFT->value,
            $payment->status
        );

        $this->assertDatabaseMissing('journal_entries', [
            'reference_type' => Payment::class,
            'reference_id' => $payment->id,
        ]);
    }

    public function test_fully_allocated_receipt_does_not_create_customer_advance(): void
    {
        $payment = Payment::create([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'payment_no' => 'RCT-VALIDATION-010',
            'payment_type' => PaymentTypeEnum::RECEIPT,
            'payment_date' => now()->toDateString(),
            'party_type' => PaymentPartyTypeEnum::CUSTOMER,
            'party_id' => $this->customer->id,
            'account_id' => $this->cashAccount->id,
            'amount' => 1000,
            'payment_method' => PaymentMethodEnum::CASH,
            'status' => DocumentStatusEnum::DRAFT->value,
        ]);

        PaymentAllocation::create([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'payment_id' => $payment->id,
            'document_type' => 'sales_invoice',
            'document_id' => 1010,
            'allocated_amount' => 1000,
        ]);

        $confirmed = app(PaymentPostingServiceInterface::class)
            ->confirm($payment);

        $this->assertSame(
            DocumentStatusEnum::CONFIRMED->value,
            $confirmed->status
        );

        $journalEntry = JournalEntry::query()
            ->where('reference_type', Payment::class)
            ->where('reference_id', $payment->id)
            ->firstOrFail();

        $journalEntry->load('lines.account');

        $this->assertCount(2, $journalEntry->lines);

        $cashLine = $journalEntry->lines
            ->firstWhere('chart_of_account_id', $this->cashAccount->id);

        $this->assertSame('1000.0000', (string) $cashLine->debit);
        $this->assertSame('0.0000', (string) $cashLine->credit);

        $receivableLine = $journalEntry->lines
            ->firstWhere(
                'account.account_code',
                AccountingAccounts::ACCOUNTS_RECEIVABLE
            );

        $this->assertNotNull($receivableLine);
        $this->assertSame('0.0000', (string) $receivableLine->debit);
        $this->assertSame('1000.0000', (string) $receivableLine->credit);

        $this->assertDatabaseMissing('journal_entry_lines', [
            'journal_entry_id' => $journalEntry->id,
            'chart_of_account_id' => ChartOfAccount::query()
                ->where('tenant_id', $this->tenant->id)
                ->where(
                    'account_code',
                    AccountingAccounts::CUSTOMER_ADVANCES
                )
                ->value('id'),
        ]);
    }

    public function test_fully_allocated_payment_does_not_create_supplier_advance(): void
    {
        $payment = Payment::create([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'payment_no' => 'PAY-VALIDATION-011',
            'payment_type' => PaymentTypeEnum::PAYMENT,
            'payment_date' => now()->toDateString(),
            'party_type' => PaymentPartyTypeEnum::SUPPLIER,
            'party_id' => $this->supplier->id,
            'account_id' => $this->cashAccount->id,
            'amount' => 1000,
            'payment_method' => PaymentMethodEnum::CASH,
            'status' => DocumentStatusEnum::DRAFT->value,
        ]);

        PaymentAllocation::create([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'payment_id' => $payment->id,
            'document_type' => 'purchase_bill',
            'document_id' => 1011,
            'allocated_amount' => 1000,
        ]);

        $confirmed = app(PaymentPostingServiceInterface::class)
            ->confirm($payment);

        $this->assertSame(
            DocumentStatusEnum::CONFIRMED->value,
            $confirmed->status
        );

        $journalEntry = JournalEntry::query()
            ->where('reference_type', Payment::class)
            ->where('reference_id', $payment->id)
            ->firstOrFail();

        $journalEntry->load('lines.account');

        $this->assertCount(2, $journalEntry->lines);

        $cashLine = $journalEntry->lines
            ->firstWhere('chart_of_account_id', $this->cashAccount->id);

        $this->assertSame('0.0000', (string) $cashLine->debit);
        $this->assertSame('1000.0000', (string) $cashLine->credit);

        $payableLine = $journalEntry->lines
            ->firstWhere(
                'account.account_code',
                AccountingAccounts::ACCOUNTS_PAYABLE
            );

        $this->assertNotNull($payableLine);
        $this->assertSame('1000.0000', (string) $payableLine->debit);
        $this->assertSame('0.0000', (string) $payableLine->credit);

        $this->assertDatabaseMissing('journal_entry_lines', [
            'journal_entry_id' => $journalEntry->id,
            'chart_of_account_id' => ChartOfAccount::query()
                ->where('tenant_id', $this->tenant->id)
                ->where(
                    'account_code',
                    AccountingAccounts::SUPPLIER_ADVANCES
                )
                ->value('id'),
        ]);
    }

    public function test_multiple_receipt_allocations_are_combined_correctly(): void
    {
        $payment = Payment::create([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'payment_no' => 'RCT-VALIDATION-012',
            'payment_type' => PaymentTypeEnum::RECEIPT,
            'payment_date' => now()->toDateString(),
            'party_type' => PaymentPartyTypeEnum::CUSTOMER,
            'party_id' => $this->customer->id,
            'account_id' => $this->cashAccount->id,
            'amount' => 1000,
            'payment_method' => PaymentMethodEnum::CASH,
            'status' => DocumentStatusEnum::DRAFT->value,
        ]);

        $openingBill = CustomerOpeningBill::create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'bill_no' => 'OB-VALIDATION-012',
            'bill_date' => now()->toDateString(),
            'amount' => 350,
            'balance_amount' => 350,
            'balance_type' => OpeningBalanceTypeEnum::DEBIT->value,
        ]);

        PaymentAllocation::create([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'payment_id' => $payment->id,
            'document_type' => 'sales_invoice',
            'document_id' => 1012,
            'allocated_amount' => 400,
        ]);

        PaymentAllocation::create([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'payment_id' => $payment->id,
            'document_type' => 'customer_opening_bill',
            'document_id' => $openingBill->id,
            'allocated_amount' => 350,
        ]);

        $confirmed = app(PaymentPostingServiceInterface::class)
            ->confirm($payment);

        $this->assertSame(
            DocumentStatusEnum::CONFIRMED->value,
            $confirmed->status
        );

        $journalEntry = JournalEntry::query()
            ->where('reference_type', Payment::class)
            ->where('reference_id', $payment->id)
            ->firstOrFail();

        $journalEntry->load('lines.account');

        $this->assertCount(3, $journalEntry->lines);

        $cashLine = $journalEntry->lines
            ->firstWhere('chart_of_account_id', $this->cashAccount->id);

        $this->assertSame('1000.0000', (string) $cashLine->debit);
        $this->assertSame('0.0000', (string) $cashLine->credit);

        $receivableLine = $journalEntry->lines
            ->firstWhere(
                'account.account_code',
                AccountingAccounts::ACCOUNTS_RECEIVABLE
            );

        $this->assertNotNull($receivableLine);
        $this->assertSame('0.0000', (string) $receivableLine->debit);
        $this->assertSame('750.0000', (string) $receivableLine->credit);

        $advanceLine = $journalEntry->lines
            ->firstWhere(
                'account.account_code',
                AccountingAccounts::CUSTOMER_ADVANCES
            );

        $this->assertNotNull($advanceLine);
        $this->assertSame('0.0000', (string) $advanceLine->debit);
        $this->assertSame('250.0000', (string) $advanceLine->credit);
    }

    public function test_multiple_payment_allocations_are_combined_correctly(): void
    {
        $payment = Payment::create([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'payment_no' => 'PAY-VALIDATION-013',
            'payment_type' => PaymentTypeEnum::PAYMENT,
            'payment_date' => now()->toDateString(),
            'party_type' => PaymentPartyTypeEnum::SUPPLIER,
            'party_id' => $this->supplier->id,
            'account_id' => $this->cashAccount->id,
            'amount' => 1000,
            'payment_method' => PaymentMethodEnum::CASH,
            'status' => DocumentStatusEnum::DRAFT->value,
        ]);

        $openingBill = SupplierOpeningBill::create([
            'tenant_id' => $this->tenant->id,
            'supplier_id' => $this->supplier->id,
            'bill_no' => 'OB-VALIDATION-024',
            'bill_date' => now()->toDateString(),
            'amount' => 350,
            'balance_amount' => 350,
            'balance_type' => OpeningBalanceTypeEnum::CREDIT->value,
        ]);

        PaymentAllocation::create([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'payment_id' => $payment->id,
            'document_type' => 'purchase_bill',
            'document_id' => 1013,
            'allocated_amount' => 400,
        ]);

        PaymentAllocation::create([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'payment_id' => $payment->id,
            'document_type' => 'supplier_opening_bill',
            'document_id' => $openingBill->id,
            'allocated_amount' => 350,
        ]);

        $confirmed = app(PaymentPostingServiceInterface::class)
            ->confirm($payment);

        $this->assertSame(
            DocumentStatusEnum::CONFIRMED->value,
            $confirmed->status
        );

        $journalEntry = JournalEntry::query()
            ->where('reference_type', Payment::class)
            ->where('reference_id', $payment->id)
            ->firstOrFail();

        $journalEntry->load('lines.account');

        $this->assertCount(3, $journalEntry->lines);

        $cashLine = $journalEntry->lines
            ->firstWhere('chart_of_account_id', $this->cashAccount->id);

        $this->assertSame('0.0000', (string) $cashLine->debit);
        $this->assertSame('1000.0000', (string) $cashLine->credit);

        $payableLine = $journalEntry->lines
            ->firstWhere(
                'account.account_code',
                AccountingAccounts::ACCOUNTS_PAYABLE
            );

        $this->assertNotNull($payableLine);
        $this->assertSame('750.0000', (string) $payableLine->debit);
        $this->assertSame('0.0000', (string) $payableLine->credit);

        $advanceLine = $journalEntry->lines
            ->firstWhere(
                'account.account_code',
                AccountingAccounts::SUPPLIER_ADVANCES
            );

        $this->assertNotNull($advanceLine);
        $this->assertSame('250.0000', (string) $advanceLine->debit);
        $this->assertSame('0.0000', (string) $advanceLine->credit);
    }

    public function test_payment_allocations_cannot_exceed_payment_amount(): void
    {
        $payment = Payment::create([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'payment_no' => 'RCT-VALIDATION-014',
            'payment_type' => PaymentTypeEnum::RECEIPT,
            'payment_date' => now()->toDateString(),
            'party_type' => PaymentPartyTypeEnum::CUSTOMER,
            'party_id' => $this->customer->id,
            'account_id' => $this->cashAccount->id,
            'amount' => 1000,
            'payment_method' => PaymentMethodEnum::CASH,
            'status' => DocumentStatusEnum::DRAFT->value,
        ]);

        $allocation = PaymentAllocation::create([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'payment_id' => $payment->id,
            'document_type' => 'sales_invoice',
            'document_id' => 1015,
            'allocated_amount' => 1200,
        ]);

        try {
            app(PaymentPostingServiceInterface::class)
                ->confirm($payment);

            $this->fail(
                'Expected BusinessException was not thrown.'
            );
        } catch (BusinessException $exception) {
            $this->assertSame(
                'Payment allocations cannot exceed payment amount.',
                $exception->getMessage()
            );
        }

        $payment->refresh();

        $this->assertSame(
            DocumentStatusEnum::DRAFT->value,
            $payment->status
        );

        $this->assertDatabaseHas('payment_allocations', [
            'id' => $allocation->id,
            'allocated_amount' => 1200,
        ]);

        $this->assertDatabaseMissing('journal_entries', [
            'reference_type' => Payment::class,
            'reference_id' => $payment->id,
        ]);
    }

    public function test_confirmation_rolls_back_when_required_account_is_missing(): void
    {
        $receivableAccount = ChartOfAccount::query()
            ->where('tenant_id', $this->tenant->id)
            ->where(
                'account_code',
                AccountingAccounts::ACCOUNTS_RECEIVABLE
            )
            ->firstOrFail();

        $receivableAccount->delete();

        $payment = Payment::create([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'payment_no' => 'RCT-VALIDATION-014',
            'payment_type' => PaymentTypeEnum::RECEIPT,
            'payment_date' => now()->toDateString(),
            'party_type' => PaymentPartyTypeEnum::CUSTOMER,
            'party_id' => $this->customer->id,
            'account_id' => $this->cashAccount->id,
            'amount' => 1000,
            'payment_method' => PaymentMethodEnum::CASH,
            'status' => DocumentStatusEnum::DRAFT->value,
        ]);

        PaymentAllocation::create([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'payment_id' => $payment->id,
            'document_type' => 'sales_invoice',
            'document_id' => 1014,
            'allocated_amount' => 1000,
        ]);

        $this->expectException(BusinessException::class);
        $this->expectExceptionMessage(
            'Account ' . AccountingAccounts::ACCOUNTS_RECEIVABLE . ' not found.'
        );

        app(PaymentPostingServiceInterface::class)
            ->confirm($payment);

        $payment->refresh();

        $this->assertSame(
            DocumentStatusEnum::DRAFT->value,
            $payment->status
        );

        $this->assertDatabaseMissing('journal_entries', [
            'reference_type' => Payment::class,
            'reference_id' => $payment->id,
        ]);

        $this->assertDatabaseHas('payment_allocations', [
            'payment_id' => $payment->id,
            'allocated_amount' => 1000,
        ]);
    }

    public function test_confirmed_payment_with_existing_journal_cannot_be_posted_again(): void
    {
        $payment = Payment::create([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'payment_no' => 'RCT-VALIDATION-015',
            'payment_type' => PaymentTypeEnum::RECEIPT,
            'payment_date' => now()->toDateString(),
            'party_type' => PaymentPartyTypeEnum::CUSTOMER,
            'party_id' => $this->customer->id,
            'account_id' => $this->cashAccount->id,
            'amount' => 1000,
            'payment_method' => PaymentMethodEnum::CASH,
            'status' => DocumentStatusEnum::DRAFT->value,
        ]);

        $confirmed = app(PaymentPostingServiceInterface::class)
            ->confirm($payment);

        $this->assertSame(
            DocumentStatusEnum::CONFIRMED->value,
            $confirmed->status
        );

        $this->assertDatabaseCount('journal_entries', 1);

        $this->expectException(BusinessException::class);
        $this->expectExceptionMessage(
            'Only draft payments can be confirmed.'
        );

        app(PaymentPostingServiceInterface::class)
            ->confirm($confirmed);

        $this->assertDatabaseCount('journal_entries', 1);
    }

    public function test_receipt_with_missing_customer_opening_bill_remains_draft(): void
    {
        $payment = Payment::create([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'payment_no' => 'RCT-VALIDATION-021',
            'payment_type' => PaymentTypeEnum::RECEIPT,
            'payment_date' => now()->toDateString(),
            'party_type' => PaymentPartyTypeEnum::CUSTOMER,
            'party_id' => $this->customer->id,
            'account_id' => $this->cashAccount->id,
            'amount' => 500,
            'payment_method' => PaymentMethodEnum::CASH,
            'status' => DocumentStatusEnum::DRAFT->value,
        ]);

        PaymentAllocation::create([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'payment_id' => $payment->id,
            'document_type' => 'customer_opening_bill',
            'document_id' => 999999,
            'allocated_amount' => 300,
        ]);

        $this->expectExceptionMessage(
            'Customer opening bill not found.'
        );

        app(PaymentPostingServiceInterface::class)
            ->confirm($payment);

        $payment->refresh();

        $this->assertSame(
            DocumentStatusEnum::DRAFT->value,
            $payment->status
        );

        $this->assertDatabaseMissing('journal_entries', [
            'reference_type' => Payment::class,
            'reference_id' => $payment->id,
        ]);
    }

    public function test_receipt_with_missing_customer_opening_bill_remains_draft_v2(): void
    {
        $payment = Payment::create([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'payment_no' => 'RCT-VALIDATION-021',
            'payment_type' => PaymentTypeEnum::RECEIPT,
            'payment_date' => now()->toDateString(),
            'party_type' => PaymentPartyTypeEnum::CUSTOMER,
            'party_id' => $this->customer->id,
            'account_id' => $this->cashAccount->id,
            'amount' => 500,
            'payment_method' => PaymentMethodEnum::CASH,
            'status' => DocumentStatusEnum::DRAFT->value,
        ]);

        PaymentAllocation::create([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'payment_id' => $payment->id,
            'document_type' => 'customer_opening_bill',
            'document_id' => 999999,
            'allocated_amount' => 300,
        ]);

        try {
            app(PaymentPostingServiceInterface::class)
                ->confirm($payment);

            $this->fail(
                'Expected customer opening bill validation to fail.'
            );
        } catch (BusinessException $exception) {
            $this->assertSame(
                'Customer opening bill not found.',
                $exception->getMessage()
            );
        }

        $payment->refresh();

        $this->assertSame(
            DocumentStatusEnum::DRAFT->value,
            $payment->status
        );

        $this->assertDatabaseMissing('journal_entries', [
            'reference_type' => Payment::class,
            'reference_id' => $payment->id,
        ]);
    }

    public function test_receipt_with_opening_bill_of_another_customer_remains_draft(): void
    {
        $otherCustomer = $this->createCustomer();

        $openingBill = CustomerOpeningBill::create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $otherCustomer->id,
            'bill_no' => 'OB-VALIDATION-022',
            'bill_date' => now()->toDateString(),
            'amount' => 500,
            'balance_amount' => 500,
            'balance_type' => OpeningBalanceTypeEnum::DEBIT->value,
        ]);

        $payment = Payment::create([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'payment_no' => 'RCT-VALIDATION-022',
            'payment_type' => PaymentTypeEnum::RECEIPT,
            'payment_date' => now()->toDateString(),
            'party_type' => PaymentPartyTypeEnum::CUSTOMER,
            'party_id' => $this->customer->id,
            'account_id' => $this->cashAccount->id,
            'amount' => 500,
            'payment_method' => PaymentMethodEnum::CASH,
            'status' => DocumentStatusEnum::DRAFT->value,
        ]);

        PaymentAllocation::create([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'payment_id' => $payment->id,
            'document_type' => 'customer_opening_bill',
            'document_id' => $openingBill->id,
            'allocated_amount' => 300,
        ]);

        try {
            app(PaymentPostingServiceInterface::class)
                ->confirm($payment);

            $this->fail(
                'Expected customer ownership validation to fail.'
            );
        } catch (BusinessException $exception) {
            $this->assertSame(
                'Customer opening bill does not belong to payment customer.',
                $exception->getMessage()
            );
        }

        $payment->refresh();

        $this->assertSame(
            DocumentStatusEnum::DRAFT->value,
            $payment->status
        );

        $this->assertDatabaseMissing('journal_entries', [
            'reference_type' => Payment::class,
            'reference_id' => $payment->id,
        ]);
    }

    public function test_payment_with_missing_supplier_opening_bill_remains_draft(): void
    {
        $payment = Payment::create([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'payment_no' => 'PAY-VALIDATION-023',
            'payment_type' => PaymentTypeEnum::PAYMENT,
            'payment_date' => now()->toDateString(),
            'party_type' => PaymentPartyTypeEnum::SUPPLIER,
            'party_id' => $this->supplier->id,
            'account_id' => $this->cashAccount->id,
            'amount' => 500,
            'payment_method' => PaymentMethodEnum::CASH,
            'status' => DocumentStatusEnum::DRAFT->value,
        ]);

        PaymentAllocation::create([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'payment_id' => $payment->id,
            'document_type' => 'supplier_opening_bill',
            'document_id' => 999999,
            'allocated_amount' => 300,
        ]);

        try {
            app(PaymentPostingServiceInterface::class)
                ->confirm($payment);

            $this->fail(
                'Expected supplier opening bill validation to fail.'
            );
        } catch (BusinessException $exception) {
            $this->assertSame(
                'Supplier opening bill not found.',
                $exception->getMessage()
            );
        }

        $payment->refresh();

        $this->assertSame(
            DocumentStatusEnum::DRAFT->value,
            $payment->status
        );

        $this->assertDatabaseMissing('journal_entries', [
            'reference_type' => Payment::class,
            'reference_id' => $payment->id,
        ]);
    }

    public function test_payment_with_opening_bill_of_another_supplier_remains_draft(): void
    {
        $otherSupplier = $this->createSupplier();

        $openingBill = SupplierOpeningBill::create([
            'tenant_id' => $this->tenant->id,
            'supplier_id' => $otherSupplier->id,
            'bill_no' => 'OB-VALIDATION-024',
            'bill_date' => now()->toDateString(),
            'amount' => 500,
            'balance_amount' => 500,
            'balance_type' => OpeningBalanceTypeEnum::CREDIT->value,
        ]);

        $payment = Payment::create([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'payment_no' => 'PAY-VALIDATION-024',
            'payment_type' => PaymentTypeEnum::PAYMENT,
            'payment_date' => now()->toDateString(),
            'party_type' => PaymentPartyTypeEnum::SUPPLIER,
            'party_id' => $this->supplier->id,
            'account_id' => $this->cashAccount->id,
            'amount' => 500,
            'payment_method' => PaymentMethodEnum::CASH,
            'status' => DocumentStatusEnum::DRAFT->value,
        ]);

        PaymentAllocation::create([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'payment_id' => $payment->id,
            'document_type' => 'supplier_opening_bill',
            'document_id' => $openingBill->id,
            'allocated_amount' => 300,
        ]);

        try {
            app(PaymentPostingServiceInterface::class)
                ->confirm($payment);

            $this->fail(
                'Expected supplier ownership validation to fail.'
            );
        } catch (BusinessException $exception) {
            $this->assertSame(
                'Supplier opening bill does not belong to payment supplier.',
                $exception->getMessage()
            );
        }

        $payment->refresh();

        $this->assertSame(
            DocumentStatusEnum::DRAFT->value,
            $payment->status
        );

        $this->assertDatabaseMissing('journal_entries', [
            'reference_type' => Payment::class,
            'reference_id' => $payment->id,
        ]);
    }

    public function test_payment_allocation_exceeds_supplier_opening_bill_balance_remains_draft(): void
    {
        $openingBill = SupplierOpeningBill::create([
            'tenant_id' => $this->tenant->id,
            'supplier_id' => $this->supplier->id,
            'bill_no' => 'OB-VALIDATION-025',
            'bill_date' => now()->toDateString(),
            'amount' => 500,
            'balance_amount' => 200,
            'balance_type' => OpeningBalanceTypeEnum::CREDIT->value,
        ]);

        $payment = Payment::create([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'payment_no' => 'PAY-VALIDATION-025',
            'payment_type' => PaymentTypeEnum::PAYMENT,
            'payment_date' => now()->toDateString(),
            'party_type' => PaymentPartyTypeEnum::SUPPLIER,
            'party_id' => $this->supplier->id,
            'account_id' => $this->cashAccount->id,
            'amount' => 500,
            'payment_method' => PaymentMethodEnum::CASH,
            'status' => DocumentStatusEnum::DRAFT->value,
        ]);

        PaymentAllocation::create([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'payment_id' => $payment->id,
            'document_type' => 'supplier_opening_bill',
            'document_id' => $openingBill->id,
            'allocated_amount' => 300,
        ]);

        try {
            app(PaymentPostingServiceInterface::class)
                ->confirm($payment);

            $this->fail(
                'Expected supplier opening bill balance validation to fail.'
            );
        } catch (BusinessException $exception) {
            $this->assertSame(
                'Payment allocation exceeds supplier opening bill balance.',
                $exception->getMessage()
            );
        }

        $payment->refresh();

        $this->assertSame(
            DocumentStatusEnum::DRAFT->value,
            $payment->status
        );

        $this->assertDatabaseMissing('journal_entries', [
            'reference_type' => Payment::class,
            'reference_id' => $payment->id,
        ]);
    }

}
