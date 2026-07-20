<?php

namespace App\Modules\Accounting\Services\Postings;

use App\Modules\Accounting\Models\ChartOfAccount;
use App\Modules\Accounting\Services\JournalEntryService;

abstract class BasePostingService
{
    public function __construct(
        protected JournalEntryService $journalEntryService
    ) {}

    /**
     * Validate posting amount.
     */
    protected function validateAmount(
        float $amount
    ): void {

        abort_if(
            $amount <= 0,
            422,
            'Amount must be greater than zero.'
        );
    }

    /**
     * Resolve account code safely.
     */
    protected function getAccountCode(
        ?ChartOfAccount $account
    ): string {

        abort_if(
            !$account,
            422,
            'Account not found.'
        );

        return $account->account_code;
    }

    /**
     * Generic journal line builder.
     */
    protected function buildEntryLine(
        string $accountCode,
        float $debit = 0,
        float $credit = 0,
        ?string $description = null
    ): array {

        return [

            'account_code' => $accountCode,

            'debit' => $debit,

            'credit' => $credit,

            'description' => $description,
        ];
    }

    /**
     * Debit helper.
     */
    protected function debit(
        string $accountCode,
        float $amount,
        ?string $description = null
    ): array {

        return $this->buildEntryLine(
            accountCode: $accountCode,
            debit: $amount,
            credit: 0,
            description: $description
        );
    }

    /**
     * Credit helper.
     */
    protected function credit(
        string $accountCode,
        float $amount,
        ?string $description = null
    ): array {

        return $this->buildEntryLine(
            accountCode: $accountCode,
            debit: 0,
            credit: $amount,
            description: $description
        );
    }

    /**
     * Create and post journal entry.
     */
    protected function createJournalEntry(
        string $entryDate,
        string $voucherType,
        string $referenceType,
        int $referenceId,
        string $description,
        array $lines
    ): void {

        abort_if(
            empty($lines),
            422,
            'Journal entry lines cannot be empty.'
        );

        $this->journalEntryService
            ->createAndPost([

                'voucher_type' => $voucherType,

                'reference_type' => $referenceType,

                'reference_id' => $referenceId,

                'entry_date' => $entryDate,

                'description' => $description,

                'status' => 'draft',

                'created_by' => auth()->id(),

                'lines' => $lines,
            ]);
    }
}