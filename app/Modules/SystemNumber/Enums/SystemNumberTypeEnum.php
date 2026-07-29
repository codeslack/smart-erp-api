<?php

namespace App\Modules\SystemNumber\Enums;

enum SystemNumberTypeEnum: string
{
    case COMPANY = 'company';

    case USER = 'user';

    case CUSTOMER = 'customer';

    case SUPPLIER = 'supplier';

    case PRODUCT = 'product';

    case WAREHOUSE = 'warehouse';

    case ACCOUNT = 'account';

    case AREA = 'area';

    case UNIT = 'unit';

    case BRAND = 'brand';

    case CATEGORY = 'category';

    public function prefix(): string
    {
        return match ($this) {

            self::COMPANY => 'CMP',

            self::USER => 'USR',

            self::CUSTOMER => 'CUS',

            self::SUPPLIER => 'SUP',

            self::PRODUCT => 'PRD',

            self::WAREHOUSE => 'WH',

            self::ACCOUNT => 'ACC',

            self::AREA => 'ARE',

            self::UNIT => 'UNT',

            self::BRAND => 'BRD',

            self::CATEGORY => 'CAT',
        };
    }

    public function isGlobal(): bool
    {
        return match ($this) {

            self::COMPANY => true,

            default => false,
        };
    }
}