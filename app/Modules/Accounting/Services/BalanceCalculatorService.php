<?php

namespace App\Modules\Accounting\Services;

use App\Modules\Accounting\Enums\AccountType;

class BalanceCalculatorService
{
    public function calculate(
        string $accountType,
        float $currentBalance,
        float $debit,
        float $credit
    ): float {

        return match ($accountType) {

            AccountType::ASSET,
            AccountType::EXPENSE =>

                $currentBalance
                + $debit
                - $credit,

            AccountType::LIABILITY,
            AccountType::EQUITY,
            AccountType::INCOME =>

                $currentBalance
                - $debit
                + $credit,

            default =>
                $currentBalance,
        };
    }

    public function openingBalance(
        string $accountType
    ): float {

        return match ($accountType) {

            AccountType::ASSET,
            AccountType::EXPENSE => 0,

            AccountType::LIABILITY,
            AccountType::EQUITY,
            AccountType::INCOME => 0,

            default => 0,
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
            ]
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
            ]
        );
    }
}