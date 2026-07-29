<?php

namespace App\Core\Enums;

enum ApprovalStatusEnum: string
{
    case DRAFT = 'DRAFT';

    case PENDING = 'PENDING';

    case APPROVED = 'APPROVED';

    case REJECTED = 'REJECTED';

    case CANCELLED = 'CANCELLED';
}