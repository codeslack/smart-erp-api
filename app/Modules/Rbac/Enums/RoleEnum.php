<?php

namespace App\Modules\Rbac\Enums;

enum RoleEnum: string
{
    case ADMINISTRATOR = 'Administrator';

    case MANAGER = 'Manager';

    case ACCOUNTANT = 'Accountant';

    case SALES_EXECUTIVE = 'Sales Executive';

    case PURCHASE_EXECUTIVE = 'Purchase Executive';

    case WAREHOUSE_MANAGER = 'Warehouse Manager';

    case VIEWER = 'Viewer';

    public static function values(): array
    {
        return array_column(
            self::cases(),
            'value'
        );
    }
}