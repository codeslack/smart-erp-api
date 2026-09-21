<?php

namespace App\Modules\Inventory\Enums;

enum ProductBatchStatusEnum: string
{
    case DRAFT = 'DRAFT';
    
    case ACTIVE = 'ACTIVE';

    case EXPIRED = 'EXPIRED';

    case DEPLETED = 'DEPLETED';

    case QUARANTINED = 'QUARANTINED';

    case INACTIVE = 'INACTIVE';
}
