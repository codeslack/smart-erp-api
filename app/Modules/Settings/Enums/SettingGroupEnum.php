<?php

namespace App\Modules\Settings\Enums;

enum SettingGroupEnum: string
{
    case COMPANY = 'company';

    case INVENTORY = 'inventory';

    case SALES = 'sales';

    case PURCHASE = 'purchase';

    case ACCOUNTING = 'accounting';

    case TAX = 'tax';
}