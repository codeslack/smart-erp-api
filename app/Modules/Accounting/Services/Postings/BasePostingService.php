<?php

namespace App\Modules\Accounting\Services\Postings;

use App\Core\Exceptions\BusinessException;

use App\Modules\Accounting\Enums\JournalEntryStatusEnum;

use App\Modules\Accounting\Models\ChartOfAccount;

use App\Modules\Accounting\Services\JournalEntry\JournalEntryService;

abstract class BasePostingService
{
    public function __construct(
        protected JournalEntryService $journalEntryService
    ) {}

    protected function validateAmount(
        string|int|float $amount
    ): void {
        $amount = (string) $amount;

        if (
            ! is_numeric($amount)
            || bccomp($amount, '0', 4) <= 0
        ) {
            throw new BusinessException(
                'Amount must be greater than zero.'
            );
        }
    }

    protected function getAccount(
        string $accountCode
    ): ChartOfAccount {
        $tenantId = tenant()?->id;

        if (! $tenantId) {
            throw new BusinessException(
                'Active tenant not found.'
            );
        }

        $account = ChartOfAccount::query()
            ->where('tenant_id', $tenantId)
            ->where('account_code', $accountCode)
            ->where('is_active', true)
            ->first();

        if (! $account) {
            throw new BusinessException(
                "Account {$accountCode} not found."
            );
        }

        return $account;
    }

    protected function getAccountUuid(
        string $accountCode
    ): string {
        return $this->getAccount($accountCode)->uuid;
    }

    protected function buildEntryLine(
        string $accountCode,
        string|int|float $debit = '0',
        string|int|float $credit = '0',
        ?string $description = null
    ): array {
        return [
            'chart_of_account_uuid'
                => $this->getAccountUuid($accountCode),

            'debit' => $this->decimal($debit),

            'credit' => $this->decimal($credit),

            'description' => $description,
        ];
    }

    protected function debit(
        string $accountCode,
        string|int|float $amount,
        ?string $description = null
    ): array {
        return $this->buildEntryLine(
            accountCode: $accountCode,
            debit: $amount,
            credit: '0',
            description: $description
        );
    }

    protected function credit(
        string $accountCode,
        string|int|float $amount,
        ?string $description = null
    ): array {
        return $this->buildEntryLine(
            accountCode: $accountCode,
            debit: '0',
            credit: $amount,
            description: $description
        );
    }

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
            'status' => JournalEntryStatusEnum::DRAFT,
            'created_by' => auth()->id(),
            'lines' => $lines,
        ]);
    }

    protected function decimal(
        string|int|float $value
    ): string {
        if ($value === null || $value === '') {
            return '0.0000';
        }

        $value = (string) $value;

        if (! is_numeric($value)) {
            throw new BusinessException(
                'Accounting amount must be numeric.'
            );
        }

        return bcadd(
            $value,
            '0',
            4
        );
    }
}