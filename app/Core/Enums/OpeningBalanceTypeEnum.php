<?php

namespace App\Core\Enums;

enum OpeningBalanceTypeEnum: string
{
    case DEBIT = 'debit';

    case CREDIT = 'credit';
}