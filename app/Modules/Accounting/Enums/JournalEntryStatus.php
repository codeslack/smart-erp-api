<?php

namespace App\Modules\Accounting\Enums;

enum JournalEntryStatusEnum: string
{
    case DRAFT = 'draft';

    case POSTED = 'posted';

    case CANCELLED = 'cancelled';

    case REVERSED = 'reversed';
}