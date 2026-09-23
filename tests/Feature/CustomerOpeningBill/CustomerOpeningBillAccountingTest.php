<?php

namespace Tests\Feature\CustomerOpeningBill;

use Tests\TestCase;
use Tests\Support\CreatesTenant;

use Illuminate\Foundation\Testing\RefreshDatabase;

use App\Core\Exceptions\BusinessException;
use App\Core\Tenant\TenantManager;
use App\Core\Enums\OpeningBalanceTypeEnum;

use App\Modules\Tenant\Models\Tenant;
use App\Modules\Customer\Models\Customer;
use App\Modules\Customer\Services\CustomerService;
use App\Modules\CustomerOpeningBill\Models\CustomerOpeningBill;

use App\Modules\Accounting\Services\AccountingSetupService;
use App\Modules\Accounting\Models\ChartOfAccount;
use App\Modules\Accounting\Models\JournalEntry;
use App\Modules\Accounting\Enums\AccountingAccounts;
use App\Modules\Accounting\Enums\JournalVoucherTypeEnum;

class CustomerOpeningBillAccountingTest extends TestCase
{
    use RefreshDatabase;
    use CreatesTenant;

    protected Tenant $tenant;

    protected CustomerService $customerService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = $this->createTestTenant();

        app(TenantManager::class)
            ->setTenant($this->tenant);

        app(AccountingSetupService::class)
            ->setup($this->tenant);

        $this->customerService = app(
            CustomerService::class
        );
    }

    public function test_customer_debit_opening_bill_creates_receivable_journal(): void
    {
        $customer = $this->createCustomer([
            'opening_bills' => [
                $this->openingBill(
                    billNo: 'CUST-OPEN-001',
                    amount: 5000,
                    balanceAmount: 5000,
                    balanceType: OpeningBalanceTypeEnum::DEBIT->value,
                ),
            ],
        ]);

        $journalEntry = $this->findActiveCustomerOpeningJournal(
            $customer
        );

        $journalEntry->load('lines');

        $receivableAccount = $this->account(
            AccountingAccounts::ACCOUNTS_RECEIVABLE
        );

        $equityAccount = $this->account(
            AccountingAccounts::OPENING_BALANCE_EQUITY
        );

        $receivableLine = $journalEntry->lines->firstWhere(
            'chart_of_account_id',
            $receivableAccount->id
        );

        $equityLine = $journalEntry->lines->firstWhere(
            'chart_of_account_id',
            $equityAccount->id
        );

        $this->assertNotNull($receivableLine);
        $this->assertNotNull($equityLine);

        $this->assertEquals(
            5000,
            (float) $receivableLine->debit
        );

        $this->assertEquals(
            5000,
            (float) $equityLine->credit
        );

        $this->assertEquals(
            0,
            (float) $receivableLine->credit
        );

        $this->assertEquals(
            0,
            (float) $equityLine->debit
        );

        $this->assertEquals(
            JournalVoucherTypeEnum::CUSTOMER_OPENING_BALANCE->value,
            $journalEntry->voucher_type
        );

        $this->assertEquals(
            'posted',
            $journalEntry->status
        );
    }

    public function test_customer_credit_opening_bill_creates_customer_advance_journal(): void
    {
        $customer = $this->createCustomer([
            'opening_bills' => [
                $this->openingBill(
                    billNo: 'CUST-ADV-001',
                    amount: 2000,
                    balanceAmount: 2000,
                    balanceType: OpeningBalanceTypeEnum::CREDIT->value,
                ),
            ],
        ]);

        $journalEntry = $this->findActiveCustomerOpeningJournal(
            $customer
        );

        $journalEntry->load('lines');

        $advanceAccount = $this->account(
            AccountingAccounts::CUSTOMER_ADVANCES
        );

        $equityAccount = $this->account(
            AccountingAccounts::OPENING_BALANCE_EQUITY
        );

        $advanceLine = $journalEntry->lines->firstWhere(
            'chart_of_account_id',
            $advanceAccount->id
        );

        $equityLine = $journalEntry->lines->firstWhere(
            'chart_of_account_id',
            $equityAccount->id
        );

        $this->assertNotNull($advanceLine);
        $this->assertNotNull($equityLine);

        $this->assertEquals(
            2000,
            (float) $advanceLine->credit
        );

        $this->assertEquals(
            2000,
            (float) $equityLine->debit
        );

        $this->assertEquals(
            0,
            (float) $advanceLine->debit
        );

        $this->assertEquals(
            0,
            (float) $equityLine->credit
        );
    }

    public function test_customer_mixed_debit_and_credit_opening_bills_are_netted_correctly(): void
    {
        $customer = $this->createCustomer([
            'opening_bills' => [
                $this->openingBill(
                    billNo: 'CUST-MIX-001',
                    amount: 5000,
                    balanceAmount: 5000,
                    balanceType: OpeningBalanceTypeEnum::DEBIT->value,
                ),
                $this->openingBill(
                    billNo: 'CUST-MIX-002',
                    amount: 500,
                    balanceAmount: 500,
                    balanceType: OpeningBalanceTypeEnum::CREDIT->value,
                ),
            ],
        ]);

        $journalEntry = $this->findActiveCustomerOpeningJournal(
            $customer
        );

        $journalEntry->load('lines');

        $receivableAccount = $this->account(
            AccountingAccounts::ACCOUNTS_RECEIVABLE
        );

        $advanceAccount = $this->account(
            AccountingAccounts::CUSTOMER_ADVANCES
        );

        $equityAccount = $this->account(
            AccountingAccounts::OPENING_BALANCE_EQUITY
        );

        $receivableLine = $journalEntry->lines->firstWhere(
            'chart_of_account_id',
            $receivableAccount->id
        );

        $advanceLine = $journalEntry->lines->firstWhere(
            'chart_of_account_id',
            $advanceAccount->id
        );

        $equityLine = $journalEntry->lines->firstWhere(
            'chart_of_account_id',
            $equityAccount->id
        );

        $this->assertNotNull($receivableLine);
        $this->assertNotNull($advanceLine);
        $this->assertNotNull($equityLine);

        $this->assertEquals(
            5000,
            (float) $receivableLine->debit
        );

        $this->assertEquals(
            500,
            (float) $advanceLine->credit
        );

        $this->assertEquals(
            4500,
            (float) $equityLine->credit
        );

        $this->assertEquals(
            0,
            (float) $equityLine->debit
        );

        $this->assertEquals(
            5000,
            (float) $journalEntry->lines->sum('debit')
        );

        $this->assertEquals(
            5000,
            (float) $journalEntry->lines->sum('credit')
        );
    }

    public function test_customer_opening_bill_with_zero_outstanding_does_not_create_journal(): void
    {
        $customer = $this->createCustomer([
            'opening_bills' => [
                $this->openingBill(
                    billNo: 'CUST-PAID-001',
                    amount: 3000,
                    balanceAmount: 0,
                    balanceType: OpeningBalanceTypeEnum::DEBIT->value,
                ),
            ],
        ]);

        $this->assertDatabaseHas(
            'customer_opening_bills',
            [
                'customer_id' => $customer->id,
                'bill_no' => 'CUST-PAID-001',
            ]
        );

        $this->assertDatabaseMissing(
            'journal_entries',
            [
                'reference_type' => Customer::class,
                'reference_id' => $customer->id,
                'voucher_type' => JournalVoucherTypeEnum::CUSTOMER_OPENING_BALANCE->value,
            ]
        );
    }

    public function test_customer_creation_without_opening_bills_does_not_create_journal(): void
    {
        $customer = $this->createCustomer();

        $this->assertDatabaseMissing(
            'journal_entries',
            [
                'reference_type' => Customer::class,
                'reference_id' => $customer->id,
                'voucher_type' => JournalVoucherTypeEnum::CUSTOMER_OPENING_BALANCE->value,
            ]
        );
    }

    public function test_invalid_opening_bill_rolls_back_customer_creation(): void
    {
        $this->expectException(
            BusinessException::class
        );

        try {
            $this->customerService->create([
                'name' => 'Rollback Customer',
                'opening_bills' => [
                    $this->openingBill(
                        billNo: 'CUST-INVALID-001',
                        amount: 1000,
                        balanceAmount: 2000,
                        balanceType: OpeningBalanceTypeEnum::DEBIT->value,
                    ),
                ],
            ]);
        } finally {
            $this->assertDatabaseMissing(
                'customers',
                [
                    'name' => 'Rollback Customer',
                ]
            );

            $this->assertDatabaseMissing(
                'customer_opening_bills',
                [
                    'bill_no' => 'CUST-INVALID-001',
                ]
            );
        }
    }

    public function test_updating_opening_bill_reverses_old_journal_and_posts_new_journal(): void
    {
        $customer = $this->createCustomer([
            'opening_bills' => [
                $this->openingBill(
                    billNo: 'CUST-UPD-001',
                    amount: 5000,
                    balanceAmount: 5000,
                    balanceType: OpeningBalanceTypeEnum::DEBIT->value,
                ),
            ],
        ]);

        $oldJournal = $this->findActiveCustomerOpeningJournal(
            $customer
        );

        $openingBill = CustomerOpeningBill::query()
            ->where('customer_id', $customer->id)
            ->where('bill_no', 'CUST-UPD-001')
            ->firstOrFail();

        $this->customerService->update($customer, [
            'opening_bills' => [
                [
                    'uuid' => $openingBill->uuid,
                    'bill_no' => 'CUST-UPD-001',
                    'bill_date' => now()->toDateString(),
                    'due_date' => now()->addDays(30)->toDateString(),
                    'amount' => 7000,
                    'balance_amount' => 7000,
                    'balance_type' => OpeningBalanceTypeEnum::DEBIT->value,
                    'notes' => 'Updated opening balance',
                ],
            ],
        ]);

        $oldJournal->refresh();
        $oldJournal->load('reversal');

        $this->assertNotNull(
            $oldJournal->reversal
        );

        $this->assertEquals(
            'posted',
            $oldJournal->reversal->status
        );

        $newJournal = $this->findActiveCustomerOpeningJournal(
            $customer->fresh()
        );

        $this->assertNotEquals(
            $oldJournal->id,
            $newJournal->id
        );

        $openingBill->refresh();

        $this->assertEquals(
            7000,
            (float) $openingBill->amount
        );

        $this->assertEquals(
            7000,
            (float) $openingBill->balance_amount
        );

        $newJournal->load('lines');

        $receivableAccount = $this->account(
            AccountingAccounts::ACCOUNTS_RECEIVABLE
        );

        $receivableLine = $newJournal->lines->firstWhere(
            'chart_of_account_id',
            $receivableAccount->id
        );

        $this->assertNotNull($receivableLine);

        $this->assertEquals(
            7000,
            (float) $receivableLine->debit
        );

        $this->assertEquals(
            7000,
            (float) $newJournal->lines->sum('debit')
        );

        $this->assertEquals(
            7000,
            (float) $newJournal->lines->sum('credit')
        );
    }

    public function test_adding_opening_bill_reverses_old_journal_and_posts_combined_balance(): void
    {
        $customer = $this->createCustomer([
            'opening_bills' => [
                $this->openingBill(
                    billNo: 'CUST-ADD-001',
                    amount: 3000,
                    balanceAmount: 3000,
                    balanceType: OpeningBalanceTypeEnum::DEBIT->value,
                ),
            ],
        ]);

        $oldJournal = $this->findActiveCustomerOpeningJournal(
            $customer
        );

        $existingBill = CustomerOpeningBill::query()
            ->where('customer_id', $customer->id)
            ->where('bill_no', 'CUST-ADD-001')
            ->firstOrFail();

        $this->customerService->update($customer, [
            'opening_bills' => [
                [
                    'uuid' => $existingBill->uuid,
                    'bill_no' => 'CUST-ADD-001',
                    'bill_date' => now()->toDateString(),
                    'due_date' => now()->addDays(30)->toDateString(),
                    'amount' => 3000,
                    'balance_amount' => 3000,
                    'balance_type' => OpeningBalanceTypeEnum::DEBIT->value,
                ],
                $this->openingBill(
                    billNo: 'CUST-ADD-002',
                    amount: 2000,
                    balanceAmount: 2000,
                    balanceType: OpeningBalanceTypeEnum::DEBIT->value,
                ),
            ],
        ]);

        $oldJournal->refresh();
        $oldJournal->load('reversal');

        $this->assertNotNull(
            $oldJournal->reversal
        );

        $newJournal = $this->findActiveCustomerOpeningJournal(
            $customer->fresh()
        );

        $this->assertNotEquals(
            $oldJournal->id,
            $newJournal->id
        );

        $newJournal->load('lines');

        $receivableAccount = $this->account(
            AccountingAccounts::ACCOUNTS_RECEIVABLE
        );

        $receivableLine = $newJournal->lines->firstWhere(
            'chart_of_account_id',
            $receivableAccount->id
        );

        $this->assertNotNull($receivableLine);

        $this->assertEquals(
            5000,
            (float) $receivableLine->debit
        );

        $this->assertEquals(
            5000,
            (float) $newJournal->lines->sum('debit')
        );

        $this->assertEquals(
            5000,
            (float) $newJournal->lines->sum('credit')
        );

        $this->assertEquals(
            2,
            CustomerOpeningBill::query()
                ->where('customer_id', $customer->id)
                ->count()
        );
    }

    public function test_deleting_opening_bill_reverses_old_journal_and_posts_remaining_balance(): void
    {
        $customer = $this->createCustomer([
            'opening_bills' => [
                $this->openingBill(
                    billNo: 'CUST-DEL-001',
                    amount: 3000,
                    balanceAmount: 3000,
                    balanceType: OpeningBalanceTypeEnum::DEBIT->value,
                ),
                $this->openingBill(
                    billNo: 'CUST-DEL-002',
                    amount: 2000,
                    balanceAmount: 2000,
                    balanceType: OpeningBalanceTypeEnum::DEBIT->value,
                ),
            ],
        ]);

        $oldJournal = $this->findActiveCustomerOpeningJournal(
            $customer
        );

        $billToDelete = CustomerOpeningBill::query()
            ->where('customer_id', $customer->id)
            ->where('bill_no', 'CUST-DEL-001')
            ->firstOrFail();

        $remainingBill = CustomerOpeningBill::query()
            ->where('customer_id', $customer->id)
            ->where('bill_no', 'CUST-DEL-002')
            ->firstOrFail();

        $this->customerService->update($customer, [
            'opening_bills' => [
                [
                    'uuid' => $billToDelete->uuid,
                    'delete' => true,
                ],
                [
                    'uuid' => $remainingBill->uuid,
                    'bill_no' => 'CUST-DEL-002',
                    'bill_date' => now()->toDateString(),
                    'due_date' => now()->addDays(30)->toDateString(),
                    'amount' => 2000,
                    'balance_amount' => 2000,
                    'balance_type' => OpeningBalanceTypeEnum::DEBIT->value,
                ],
            ],
        ]);

        $oldJournal->refresh();
        $oldJournal->load('reversal');

        $this->assertNotNull(
            $oldJournal->reversal
        );

        $newJournal = $this->findActiveCustomerOpeningJournal(
            $customer->fresh()
        );

        $this->assertNotEquals(
            $oldJournal->id,
            $newJournal->id
        );

        $newJournal->load('lines');

        $receivableAccount = $this->account(
            AccountingAccounts::ACCOUNTS_RECEIVABLE
        );

        $receivableLine = $newJournal->lines->firstWhere(
            'chart_of_account_id',
            $receivableAccount->id
        );

        $this->assertNotNull($receivableLine);

        $this->assertEquals(
            2000,
            (float) $receivableLine->debit
        );

        $this->assertEquals(
            2000,
            (float) $newJournal->lines->sum('debit')
        );

        $this->assertEquals(
            2000,
            (float) $newJournal->lines->sum('credit')
        );

        $this->assertSoftDeleted(
            'customer_opening_bills',
            [
                'id' => $billToDelete->id,
            ]
        );

        $this->assertEquals(
            1,
            CustomerOpeningBill::query()
                ->where('customer_id', $customer->id)
                ->count()
        );
    }

    public function test_updating_customer_without_opening_bills_does_not_touch_accounting(): void
    {
        $customer = $this->createCustomer([
            'opening_bills' => [
                $this->openingBill(
                    billNo: 'CUST-NO-CHANGE-001',
                    amount: 4000,
                    balanceAmount: 4000,
                    balanceType: OpeningBalanceTypeEnum::DEBIT->value,
                ),
            ],
        ]);

        $oldJournal = $this->findActiveCustomerOpeningJournal(
            $customer
        );

        $this->customerService->update($customer, [
            'name' => 'Updated Customer Name',
        ]);

        $this->assertDatabaseHas(
            'journal_entries',
            [
                'id' => $oldJournal->id,
                'status' => 'posted',
            ]
        );

        $this->assertDatabaseMissing(
            'journal_entries',
            [
                'reversal_of_journal_entry_id' => $oldJournal->id,
            ]
        );

        $this->assertEquals(
            1,
            JournalEntry::query()
                ->where('reference_type', Customer::class)
                ->where('reference_id', $customer->id)
                ->where(
                    'voucher_type',
                    JournalVoucherTypeEnum::CUSTOMER_OPENING_BALANCE->value
                )
                ->count()
        );
    }

    public function test_cross_customer_opening_bill_uuid_throws_business_exception_and_keeps_accounting_unchanged(): void
    {
        $customerOne = $this->createCustomer([
            'opening_bills' => [
                $this->openingBill(
                    billNo: 'CUST-CROSS-001',
                    amount: 5000,
                    balanceAmount: 5000,
                    balanceType: OpeningBalanceTypeEnum::DEBIT->value,
                ),
            ],
        ]);

        $customerTwo = $this->createCustomer([
            'opening_bills' => [
                $this->openingBill(
                    billNo: 'CUST-CROSS-002',
                    amount: 2000,
                    balanceAmount: 2000,
                    balanceType: OpeningBalanceTypeEnum::DEBIT->value,
                ),
            ],
        ]);

        $customerOneJournal = $this->findActiveCustomerOpeningJournal(
            $customerOne
        );

        $customerTwoBill = CustomerOpeningBill::query()
            ->where('customer_id', $customerTwo->id)
            ->firstOrFail();

        $this->expectException(
            BusinessException::class
        );

        try {
            $this->customerService->update($customerOne, [
                'opening_bills' => [
                    [
                        'uuid' => $customerTwoBill->uuid,
                        'bill_no' => 'CUST-CROSS-002',
                        'bill_date' => now()->toDateString(),
                        'due_date' => now()->addDays(30)->toDateString(),
                        'amount' => 2000,
                        'balance_amount' => 2000,
                        'balance_type' => OpeningBalanceTypeEnum::DEBIT->value,
                    ],
                ],
            ]);
        } finally {
            $this->assertDatabaseHas(
                'journal_entries',
                [
                    'id' => $customerOneJournal->id,
                    'status' => 'posted',
                ]
            );

            $this->assertDatabaseMissing(
                'journal_entries',
                [
                    'reversal_of_journal_entry_id' => $customerOneJournal->id,
                ]
            );

            $this->assertDatabaseHas(
                'customer_opening_bills',
                [
                    'id' => $customerTwoBill->id,
                    'customer_id' => $customerTwo->id,
                ]
            );
        }
    }

    protected function createCustomer(array $data = []): Customer
    {
        return $this->customerService->create(
            array_merge(
                [
                    'name' => 'Test Customer ' . uniqid(),
            ],
                $data
            )
        );
    }

    protected function openingBill(
        string $billNo,
        float $amount,
        float $balanceAmount,
        string $balanceType,
    ): array {
        return [
            'bill_no' => $billNo,
            'bill_date' => now()->toDateString(),
            'due_date' => now()->addDays(30)->toDateString(),
            'amount' => $amount,
            'balance_amount' => $balanceAmount,
            'balance_type' => $balanceType,
            'notes' => 'Customer opening balance test',
        ];
    }

    protected function findActiveCustomerOpeningJournal(
        Customer $customer
    ): JournalEntry {
        return JournalEntry::query()
            ->where(
                'reference_type',
                Customer::class
            )
            ->where(
                'reference_id',
                $customer->id
            )
            ->where(
                'voucher_type',
                JournalVoucherTypeEnum::CUSTOMER_OPENING_BALANCE->value
            )
            ->where(
                'status',
                'posted'
            )
            ->whereNull(
                'reversal_of_journal_entry_id'
            )
            ->whereDoesntHave(
                'reversal'
            )
            ->firstOrFail();
    }

    protected function account(
        string $accountCode
    ): ChartOfAccount {
        return ChartOfAccount::query()
            ->where(
                'account_code',
                $accountCode
            )
            ->firstOrFail();
    }
}