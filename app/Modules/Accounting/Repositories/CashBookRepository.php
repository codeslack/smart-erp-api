<?php

namespace App\Modules\Accounting\Repositories;

use App\Modules\Accounting\Models\ChartOfAccount;
use App\Modules\Accounting\Models\JournalEntryLine;
use App\Modules\Accounting\Repositories\Contracts\CashBookRepositoryInterface;

class CashBookRepository
    implements CashBookRepositoryInterface
{
    public function getCashBook(
        ?string $fromDate = null,
        ?string $toDate = null
    ): array {

        $cashAccount = ChartOfAccount::query()

            ->where(
                'account_code',
                '1000'
            )

            ->firstOrFail();

        /*
        |--------------------------------------------------------------------------
        | Opening Balance
        |--------------------------------------------------------------------------
        */

        $openingBalance = JournalEntryLine::query()

            ->where(
                'chart_of_account_id',
                $cashAccount->id
            )

            ->when(
                $fromDate,
                fn ($q) =>
                $q->whereHas(
                    'journalEntry',
                    fn ($journal) =>
                    $journal->whereDate(
                        'entry_date',
                        '<',
                        $fromDate
                    )
                )
            )

            ->get()

            ->sum(function ($line) {

                return
                    (float) $line->debit
                    -
                    (float) $line->credit;
            });

        /*
        |--------------------------------------------------------------------------
        | Cash Transactions
        |--------------------------------------------------------------------------
        */

        $lines = JournalEntryLine::query()

            ->with('journalEntry')

            ->where(
                'chart_of_account_id',
                $cashAccount->id
            )

            ->when(
                $fromDate,
                fn ($q) =>
                $q->whereHas(
                    'journalEntry',
                    fn ($journal) =>
                    $journal->whereDate(
                        'entry_date',
                        '>=',
                        $fromDate
                    )
                )
            )

            ->when(
                $toDate,
                fn ($q) =>
                $q->whereHas(
                    'journalEntry',
                    fn ($journal) =>
                    $journal->whereDate(
                        'entry_date',
                        '<=',
                        $toDate
                    )
                )
            )

            ->get();

        $transactions = collect();

        foreach ($lines as $line) {

            $transactions->push([

                'date'
                    => $line
                        ->journalEntry
                        ->entry_date,

                'voucher_no'
                    => $line
                        ->journalEntry
                        ->voucher_no,

                'description'
                    => $line
                        ->journalEntry
                        ->description,

                'debit'
                    => (float) $line->debit,

                'credit'
                    => (float) $line->credit,
            ]);
        }

        /*
        |--------------------------------------------------------------------------
        | Sort
        |--------------------------------------------------------------------------
        */

        $transactions = $transactions

            ->sortBy([
                ['date', 'asc'],
                ['voucher_no', 'asc'],
            ])

            ->values();

        /*
        |--------------------------------------------------------------------------
        | Running Balance
        |--------------------------------------------------------------------------
        */

        $runningBalance = $openingBalance;

        $transactions = $transactions

            ->map(function (
                $row
            ) use (
                &$runningBalance
            ) {

                $runningBalance +=
                    $row['debit'];

                $runningBalance -=
                    $row['credit'];

                $row['balance']
                    = $runningBalance;

                return $row;
            });

        return [

            'opening_balance'
                => $openingBalance,

            'transactions'
                => $transactions,

            'closing_balance'
                => $runningBalance,
        ];
    }
}