<?php

namespace App\Modules\Tenant\Enums;

enum BusinessTypeEnum: string
{
    case GENERAL = 'GENERAL';

    case MEDICINE = 'MEDICINE';

    case MOBILE = 'MOBILE';

    case COMPUTER = 'COMPUTER';

    case RETAIL = 'RETAIL';

    case SPARE_PARTS = 'SPARE_PARTS';

    case MANUFACTURING = 'MANUFACTURING';

    case DISTRIBUTION = 'DISTRIBUTION';

    case SERVICE = 'SERVICE';
}