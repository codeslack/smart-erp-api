<?php

namespace App\Modules\Accounting\Services\JournalEntry;

use App\Core\Exceptions\BusinessException;
use Illuminate\Support\Facades\DB;

use App\Modules\Accounting\Enums\JournalEntryStatusEnum;

use App\Modules\Accounting\Models\JournalEntry;
use App\Modules\Accounting\Models\ChartOfAccount;

use App\Modules\Accounting\Services\AccountLedgerService;
use App\Modules\Accounting\Services\BalanceCalculatorService;

class JournalEntryPoster
{
    public function __construct(
        protected AccountLedgerService $ledgerService,
        protected BalanceCalculatorService $balanceCalculator,
        protected JournalEntryValidator $validator,
    ) {}

    public function post(
        JournalEntry $journalEntry
    ): JournalEntry {
        return DB::transaction(
            function () use ($journalEntry) {

                $journalEntry = JournalEntry::query()
                    ->whereKey($journalEntry->id)
                    ->lockForUpdate()
                    ->first();

                if (! $journalEntry) {
                    throw new BusinessException(
                        'Journal entry not found.'
                    );
                }

                if (
                    $journalEntry->status
                    !== JournalEntryStatusEnum::DRAFT
                ) {
                    throw new BusinessException(
                        'Only draft journal entries can be posted.'
                    );
                }

                $journalEntry->load([
                    'lines.account',
                ]);

                if ($journalEntry->lines->isEmpty()) {
                    throw new BusinessException(
                        'Journal entry has no lines.'
                    );
                }

                foreach ($journalEntry->lines as $line) {
                    if (! $line->account) {
                        throw new BusinessException(
                            'Journal entry account not found.'
                        );
                    }

                    if (
                        (int) $line->account->tenant_id
                        !== (int) $journalEntry->tenant_id
                    ) {
                        throw new BusinessException(
                            'Journal entry contains an account from another tenant.'
                        );
                    }
                }

                $this->validator->validateLines(
                    $journalEntry->lines
                        ->map(fn ($line) => [
                            'debit' => (string) $line->debit,
                            'credit' => (string) $line->credit,
                        ])
                        ->all()
                );

                $this->ledgerService->createFromJournal(
                    $journalEntry
                );

                $this->updateAccountBalances(
                    $journalEntry
                );

                $journalEntry->update([
                    'status' => JournalEntryStatusEnum::POSTED,
                ]);

                return $journalEntry
                    ->fresh()
                    ->load(['lines.account']);
            }
        );
    }

    protected function updateAccountBalances(
        JournalEntry $journalEntry
    ): void {
        foreach ($journalEntry->lines as $line) {
            $account = ChartOfAccount::query()
                ->where('tenant_id', $journalEntry->tenant_id)
                ->whereKey($line->chart_of_account_id)
                ->lockForUpdate()
                ->first();

            if (! $account) {
                throw new BusinessException(
                    'Journal entry account not found.'
                );
            }

            $account->update([
                'current_balance' =>
                    $this->balanceCalculator->calculate(
                        accountType: $account->account_type,
                        currentBalance: (string)
                            $account->current_balance,
                        debit: (string) $line->debit,
                        credit: (string) $line->credit,
                    ),
            ]);
        }
    }
}