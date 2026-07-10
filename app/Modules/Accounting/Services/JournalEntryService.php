<?php

namespace App\Modules\Accounting\Services;

use Illuminate\Support\Facades\DB;
use App\Modules\Accounting\Enums\AccountType;
use App\Modules\Accounting\Models\JournalEntry;
use App\Modules\Accounting\Models\ChartOfAccount;
use App\Modules\Accounting\Models\JournalEntryLine;
use App\Modules\Accounting\Services\AccountLedgerService;
use App\Modules\Accounting\Repositories\Contracts\JournalEntryRepositoryInterface;

class JournalEntryService
{
    public function __construct(
        protected JournalEntryRepositoryInterface $repository,
        protected AccountLedgerService $ledgerService
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

            $journalEntry = $this->repository->create([
                ...$data,
                'voucher_no' => 'TEMP',
            ]);

            $journalEntry->update([
                'voucher_no' => sprintf(
                    'JV-%06d',
                    $journalEntry->id
                ),
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

            $account = $line->account;

            $balance = (float)
            $account->current_balance;

            switch ($account->account_type) {

                case AccountType::ASSET:

                case AccountType::EXPENSE:

                    $balance +=
                        (float) $line->debit;

                    $balance -=
                        (float) $line->credit;

                    break;

                case AccountType::LIABILITY:

                case AccountType::EQUITY:

                case AccountType::INCOME:

                    $balance -=
                        (float) $line->debit;

                    $balance +=
                        (float) $line->credit;

                    break;
            }

            $account->update([

                'current_balance'
                => $balance,
            ]);
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
}
