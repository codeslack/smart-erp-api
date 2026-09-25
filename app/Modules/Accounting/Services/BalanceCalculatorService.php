<?php

namespace App\Modules\Accounting\Services;

use App\Modules\Accounting\Enums\AccountType;

class BalanceCalculatorService
{
    private const SCALE = 4;

    public function calculate(
        string $accountType,
        string $currentBalance,
        string $debit,
        string $credit
    ): string {
        return match ($accountType) {
            AccountType::ASSET,
            AccountType::EXPENSE => bcadd(
                bcsub(
                    $currentBalance,
                    $credit,
                    self::SCALE
                ),
                $debit,
                self::SCALE
            ),

            AccountType::LIABILITY,
            AccountType::EQUITY,
            AccountType::INCOME => bcadd(
                bcsub(
                    $currentBalance,
                    $debit,
                    self::SCALE
                ),
                $credit,
                self::SCALE
            ),

            default => $currentBalance,
        };
    }

    public function isDebitNormalBalance(
        string $accountType
    ): bool {
        return in_array(
            $accountType,
            [
                AccountType::ASSET,
                AccountType::EXPENSE,
            ],
            true
        );
    }

    public function isCreditNormalBalance(
        string $accountType
    ): bool {
        return in_array(
            $accountType,
            [
                AccountType::LIABILITY,
                AccountType::EQUITY,
                AccountType::INCOME,
            ],
            true
        );
    }
}