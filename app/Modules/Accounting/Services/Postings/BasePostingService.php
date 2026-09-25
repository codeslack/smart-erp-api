<?php

namespace App\Modules\Accounting\Services\Postings;

use App\Core\Exceptions\BusinessException;

use App\Modules\Accounting\Models\ChartOfAccount;

use App\Modules\Accounting\Services\JournalEntry\JournalEntryService;

abstract class BasePostingService
{
    public function __construct(
        protected JournalEntryService $journalEntryService
    ) {}

    /**
     * Validate posting amount.
     */
    protected function validateAmount(
        string $amount
    ): void {
        if (bccomp($amount, '0', 4) <= 0) {
            throw new BusinessException(
                'Amount must be greater than zero.'
            );
        }
    }

    /**
     * Resolve account code safely.
     */
    protected function getAccountCode(
        ?ChartOfAccount $account
    ): string {
        if (! $account) {
            throw new BusinessException(
                'Account not found.'
            );
        }

        return $account->account_code;
    }

    /**
     * Generic journal line builder.
     */
    protected function buildEntryLine(
        string $accountCode,
        string $debit = '0',
        string $credit = '0',
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
        string $amount,
        ?string $description = null
    ): array {
        return $this->buildEntryLine(
            accountCode: $accountCode,
            debit: $amount,
            credit: '0',
            description: $description
        );
    }

    /**
     * Credit helper.
     */
    protected function credit(
        string $accountCode,
        string $amount,
        ?string $description = null
    ): array {
        return $this->buildEntryLine(
            accountCode: $accountCode,
            debit: '0',
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
        if (empty($lines)) {
            throw new BusinessException(
                'Journal entry lines cannot be empty.'
            );
        }

        $this->journalEntryService->createAndPost([
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