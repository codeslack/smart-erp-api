<?php

namespace Tests\Feature\Accounting;

use Tests\TestCase;
use Tests\Support\CreatesTenant;
use Illuminate\Foundation\Testing\RefreshDatabase;

use App\Core\Exceptions\BusinessException;

use App\Modules\Accounting\Enums\JournalEntryStatusEnum;

use App\Modules\Accounting\Models\ChartOfAccount;
use App\Modules\Accounting\Models\JournalEntry;

use App\Modules\Accounting\Enums\AccountingAccounts;
use App\Modules\Accounting\Enums\JournalVoucherTypeEnum;

use App\Modules\Accounting\Services\AccountingSetupService;
use App\Modules\Accounting\Services\JournalEntry\JournalEntryService;
use App\Modules\Accounting\Services\AccountingReversalService;

use App\Modules\OpeningStock\Models\OpeningStock;

class AccountingReversalTest extends TestCase
{
    use RefreshDatabase;
    use CreatesTenant;

    public function test_accounting_entry_can_be_reversed(): void
    {
        $tenant = $this->createTestTenant();

        app(AccountingSetupService::class)
            ->setup($tenant);

        $inventoryAccount =
            ChartOfAccount::query()
                ->where(
                    'account_code',
                    AccountingAccounts::INVENTORY
                )
                ->firstOrFail();

        $equityAccount =
            ChartOfAccount::query()
                ->where(
                    'account_code',
                    AccountingAccounts::OPENING_BALANCE_EQUITY
                )
                ->firstOrFail();

        $inventoryBalanceBefore =
            (float) $inventoryAccount->current_balance;

        $equityBalanceBefore =
            (float) $equityAccount->current_balance;

        $journalEntry = app(
            JournalEntryService::class
        )->createAndPost([
            'voucher_type' =>
                JournalVoucherTypeEnum::OPENING_STOCK->value,

            'reference_type' =>
                OpeningStock::class,

            'reference_id' =>
                1,

            'entry_date' =>
                now()->toDateString(),

            'description' =>
                'Test Opening Stock',

            'status' =>
                'draft',

            'created_by' =>
                auth()->id(),

            'lines' => [
                [
                    'account_code' =>
                        AccountingAccounts::INVENTORY,

                    'debit' =>
                        1000,

                    'credit' =>
                        0,
                ],
                [
                    'account_code' =>
                        AccountingAccounts::OPENING_BALANCE_EQUITY,

                    'debit' =>
                        0,

                    'credit' =>
                        1000,
                ],
            ],
        ]);

        $this->assertEquals(
            JournalEntryStatusEnum::POSTED,
            $journalEntry->status
        );

        $reversal =
            app(AccountingReversalService::class)
                ->reverse($journalEntry);

        $this->assertEquals(
            JournalEntryStatusEnum::POSTED,
            $reversal->status
        );

        $this->assertEquals(
            $journalEntry->id,
            $reversal->reversal_of_journal_entry_id
        );

        $reversal->load('lines');

        $this->assertCount(
            2,
            $reversal->lines
        );

        $inventoryLine =
            $reversal->lines
                ->firstWhere(
                    'chart_of_account_id',
                    $inventoryAccount->id
                );

        $equityLine =
            $reversal->lines
                ->firstWhere(
                    'chart_of_account_id',
                    $equityAccount->id
                );

        $this->assertNotNull(
            $inventoryLine
        );

        $this->assertNotNull(
            $equityLine
        );

        $this->assertEquals(
            0,
            (float) $inventoryLine->debit
        );

        $this->assertEquals(
            1000,
            (float) $inventoryLine->credit
        );

        $this->assertEquals(
            1000,
            (float) $equityLine->debit
        );

        $this->assertEquals(
            0,
            (float) $equityLine->credit
        );

        $this->assertEquals(
            $inventoryBalanceBefore,
            (float) $inventoryAccount
                ->fresh()
                ->current_balance
        );

        $this->assertEquals(
            $equityBalanceBefore,
            (float) $equityAccount
                ->fresh()
                ->current_balance
        );

        $this->assertEquals(
            JournalEntryStatusEnum::POSTED,
            $journalEntry
                ->fresh()
                ->status
        );

        $this->assertNotNull(
            $journalEntry
                ->fresh()
                ->reversal
        );
    }

    public function test_accounting_entry_cannot_be_reversed_twice(): void
    {
        $tenant = $this->createTestTenant();

        app(AccountingSetupService::class)
            ->setup($tenant);

        $journalEntry = app(
            JournalEntryService::class
        )->createAndPost([
            'voucher_type' =>
                JournalVoucherTypeEnum::OPENING_STOCK->value,

            'reference_type' =>
                OpeningStock::class,

            'reference_id' =>
                1,

            'entry_date' =>
                now()->toDateString(),

            'description' =>
                'Test Opening Stock',

            'status' =>
                'draft',

            'created_by' =>
                auth()->id(),

            'lines' => [
                [
                    'account_code' =>
                        AccountingAccounts::INVENTORY,

                    'debit' =>
                        1000,

                    'credit' =>
                        0,
                ],
                [
                    'account_code' =>
                        AccountingAccounts::OPENING_BALANCE_EQUITY,

                    'debit' =>
                        0,

                    'credit' =>
                        1000,
                ],
            ],
        ]);

        $service =
            app(AccountingReversalService::class);

        $service->reverse(
            $journalEntry
        );

        $this->expectException(
            BusinessException::class
        );

        $service->reverse(
            $journalEntry->fresh()
        );

        $this->assertEquals(
            2,
            JournalEntry::count()
        );
    }
}