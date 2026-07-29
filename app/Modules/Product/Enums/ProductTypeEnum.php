<?php

namespace App\Modules\Product\Enums;

enum ProductTypeEnum: string
{
    case GENERAL = 'GENERAL';

    case MEDICINE = 'MEDICINE';

    case MOBILE = 'MOBILE';

    case COMPUTER = 'COMPUTER';

    case PERIPHERAL = 'PERIPHERAL';

    case ELECTRONICS = 'ELECTRONICS';

    case SERVICE = 'SERVICE';

    case RAW_MATERIAL = 'RAW_MATERIAL';

    case FINISHED_GOOD = 'FINISHED_GOOD';

    case SPARE_PART = 'SPARE_PART';
}