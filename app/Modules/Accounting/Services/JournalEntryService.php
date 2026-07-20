<?php

namespace App\Modules\Accounting\Services;

use Illuminate\Support\Facades\DB;
use App\Modules\Accounting\Models\JournalEntry;
use App\Modules\Accounting\Models\ChartOfAccount;
use App\Modules\Accounting\Models\JournalEntryLine;
use App\Modules\Accounting\Services\AccountLedgerService;
use App\Modules\Accounting\Repositories\Contracts\JournalEntryRepositoryInterface;

class JournalEntryService
{
    public function __construct(
        protected JournalEntryRepositoryInterface $repository,
        protected AccountLedgerService $ledgerService,
        protected BalanceCalculatorService $balanceCalculator,
    ) {}

    public function getAll()
    {
        return $this->repository->paginate();
    }

    public function find(
        int|string $id
    ) {
        return $this->repository->find(
            $id
        );
    }

    public function create(
        array $data
    ): JournalEntry {

        return DB::transaction(function () use ($data) {

            $lines = $data['lines'];

            $this->validateLines($lines);

            unset($data['lines']);

            $voucherNo = nextDocumentNumber(
                'journal_entry',
                'JV'
            );

            $journalEntry = $this->repository->create([
                ...$data,
                'voucher_no' => $voucherNo,
            ]);

            foreach ($lines as $line) {

                $account = $this->resolveAccount(
                    $line,
                    $journalEntry->tenant_id
                );

                JournalEntryLine::create([
                    'tenant_id'           => $journalEntry->tenant_id,
                    'journal_entry_id'    => $journalEntry->id,
                    'chart_of_account_id' => $account->id,
                    'debit'               => $line['debit'] ?? 0,
                    'credit'              => $line['credit'] ?? 0,
                    'description'         => $line['description'] ?? null,
                ]);
            }

            return $journalEntry
                ->fresh()
                ->load([
                    'lines.account',
                ]);
        });
    }

    public function createAndPost(
        array $data
    ): JournalEntry {

        $journalEntry = $this->create(
            $data
        );

        return $this->post(
            $journalEntry
        );
    }

    public function post(
        JournalEntry $journalEntry
    ): JournalEntry {

        return DB::transaction(
            function () use (
                $journalEntry
            ) {

                abort_if(
                    $journalEntry->status !== 'draft',
                    422,
                    'Only draft entries can be posted.'
                );

                $journalEntry->load([
                    'lines.account'
                ]);

                foreach ($journalEntry->lines as $line) {

                    abort_if(
                        !$line->account,
                        422,
                        'Account not found.'
                    );
                }


                $this->validateBalancedEntry(
                    $journalEntry
                );

                $this->ledgerService
                    ->createFromJournal(
                        $journalEntry
                    );

                $this->updateAccountBalances(
                    $journalEntry
                );

                $journalEntry->update([

                    'status'
                    => 'posted',
                ]);

                return $journalEntry
                    ->fresh()
                    ->load([
                        'lines.account'
                    ]);
            }
        );
    }

    public function cancel(
        JournalEntry $journalEntry
    ): JournalEntry {

        abort_if(
            $journalEntry->status === 'posted',
            422,
            'Posted entries cannot be cancelled.'
        );

        $journalEntry->update([

            'status'
            => 'cancelled',
        ]);

        return $journalEntry->fresh();
    }

    protected function validateLines(
        array $lines
    ): void {

        abort_if(
            count($lines) < 2,
            422,
            'Journal entry must contain at least two lines.'
        );

        $totalDebit = collect($lines)
            ->sum('debit');

        $totalCredit = collect($lines)
            ->sum('credit');

        abort_if(
            bccomp(
                (string) $totalDebit,
                (string) $totalCredit,
                4
            ) !== 0,
            422,
            'Journal entry is not balanced.'
        );
    }

    protected function validateBalancedEntry(
        JournalEntry $journalEntry
    ): void {

        $totalDebit = $journalEntry
            ->lines()
            ->sum('debit');

        $totalCredit = $journalEntry
            ->lines()
            ->sum('credit');

        abort_if(
            bccomp(
                (string) $totalDebit,
                (string) $totalCredit,
                4
            ) !== 0,
            422,
            'Journal entry is not balanced.'
        );
    }

    protected function updateAccountBalances(
        JournalEntry $journalEntry
    ): void {

        foreach (
            $journalEntry->lines as $line
        ) {

            $this->applyAccountBalance(
                $line
            );
        }
    }

    protected function resolveAccount(
        array $line,
        int $tenantId
    ): ChartOfAccount {

        /*
        |--------------------------------------------------------------------------
        | Manual Journal Entry
        |--------------------------------------------------------------------------
        */

        if (isset($line['chart_of_account_id'])) {

            return ChartOfAccount::query()

                ->where(
                    'tenant_id',
                    $tenantId
                )

                ->whereKey(
                    $line['chart_of_account_id']
                )

                ->firstOrFail();
        }

        /*
        |--------------------------------------------------------------------------
        | Posting Services
        |--------------------------------------------------------------------------
        */

        abort_unless(
            isset($line['account_code']),
            422,
            'Account code is required.'
        );

        return ChartOfAccount::query()

            ->where(
                'tenant_id',
                $tenantId
            )

            ->where(
                'account_code',
                $line['account_code']
            )

            ->firstOrFail();
    }

    protected function applyAccountBalance(
        JournalEntryLine $line
    ): void {

        $account = $line->account;

        $account->update([

            'current_balance' => $this->balanceCalculator
                ->calculate(

                    accountType:
                        $account->account_type,

                    currentBalance:
                        (float) $account->current_balance,

                    debit:
                        (float) $line->debit,

                    credit:
                        (float) $line->credit
                )
        ]);
    }
}
