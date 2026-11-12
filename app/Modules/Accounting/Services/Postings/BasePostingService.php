<?php

namespace App\Modules\Accounting\Services\Postings;

use App\Core\Exceptions\BusinessException;
use App\Modules\Accounting\Enums\JournalEntryStatusEnum;
use App\Modules\Accounting\Models\ChartOfAccount;
use App\Modules\Accounting\Services\JournalEntry\JournalEntryService;
use App\Modules\Settings\Models\Setting;

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
        $tenantId = tenantId();

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

    protected function getAccountCode(
        ?ChartOfAccount $account
    ): string {
        if (! $account) {
            throw new BusinessException(
                'Payment account not found.'
            );
        }

        return $account->account_code;
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
        ?string $description = null,
        string|int|float|null $foreignDebit = null,
        string|int|float|null $foreignCredit = null
    ): array {
        return [
            'chart_of_account_uuid'
                => $this->getAccountUuid($accountCode),

            'debit' => $this->decimal($debit),

            'credit' => $this->decimal($credit),

            'foreign_debit' => $foreignDebit !== null
                ? $this->decimal($foreignDebit)
                : null,

            'foreign_credit' => $foreignCredit !== null
                ? $this->decimal($foreignCredit)
                : null,

            'description' => $description,
        ];
    }

    protected function debit(
        string $accountCode,
        string|int|float $amount,
        ?string $description = null,
        string|int|float|null $foreignAmount = null
    ): array {
        return $this->buildEntryLine(
            accountCode: $accountCode,
            debit: $amount,
            credit: '0',
            description: $description,
            foreignDebit: $foreignAmount
        );
    }

    protected function credit(
        string $accountCode,
        string|int|float $amount,
        ?string $description = null,
        string|int|float|null $foreignAmount = null
    ): array {
        return $this->buildEntryLine(
            accountCode: $accountCode,
            debit: '0',
            credit: $amount,
            description: $description,
            foreignCredit: $foreignAmount
        );
    }

    protected function createJournalEntry(
        string $entryDate,
        string $voucherType,
        string $referenceType,
        int $referenceId,
        string $description,
        array $lines,
        ?string $currencyCode = null,
        string|int|float|null $exchangeRate = null
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
            'currency_code' => $currencyCode,
            'exchange_rate' => $exchangeRate !== null
                ? $this->decimalRate($exchangeRate)
                : null,
            'description' => $description,
            'status' => JournalEntryStatusEnum::DRAFT,
            'created_by' => auth()->id() ?? userId(),
            'lines' => $lines,
        ]);
    }

    /**
     * Resolve and validate a document currency against the tenant's base currency.
     *
     * @return array{currency_code: string, exchange_rate: string}
     */
    protected function resolveCurrencyContext(
        ?string $currencyCode,
        string|int|float|null $exchangeRate,
        string $documentName
    ): array {
        $baseCurrency = strtoupper(trim((string) (
            Setting::query()
                ->where('group', Setting::GROUP_COMPANY)
                ->where('key', 'currency')
                ->value('value') ?? 'INR'
        )));

        if (! preg_match('/^[A-Z]{3}$/', $baseCurrency)) {
            throw new BusinessException(
                'Company base currency is not configured correctly.'
            );
        }

        $currencyCode = strtoupper(trim(
            $currencyCode ?: $baseCurrency
        ));

        if (! preg_match('/^[A-Z]{3}$/', $currencyCode)) {
            throw new BusinessException(
                "{$documentName} currency code must be a valid three-letter code."
            );
        }

        $exchangeRate = $this->decimalRate(
            $exchangeRate ?? '1.00000000'
        );

        if (
            $currencyCode === $baseCurrency
            && bccomp($exchangeRate, '1.00000000', 8) !== 0
        ) {
            throw new BusinessException(
                "Exchange rate must be 1 when {$documentName} currency matches company base currency."
            );
        }

        return [
            'currency_code' => $currencyCode,
            'exchange_rate' => $exchangeRate,
        ];
    }

    protected function decimalRate(
        string|int|float $value
    ): string {
        $value = (string) $value;

        if (! is_numeric($value) || bccomp($value, '0', 8) <= 0) {
            throw new BusinessException('Exchange rate must be greater than zero.');
        }

        return bcadd($value, '0', 8);
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
