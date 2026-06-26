<?php

namespace App\Modules\Accounting\Enums;

class AccountType
{
    public const ASSET = 'asset';

    public const LIABILITY = 'liability';

    public const EQUITY = 'equity';

    public const INCOME = 'income';

    public const EXPENSE = 'expense';

    public static function values(): array
    {
        return [

            self::ASSET,

            self::LIABILITY,

            self::EQUITY,

            self::INCOME,

            self::EXPENSE,
        ];
    }
}
