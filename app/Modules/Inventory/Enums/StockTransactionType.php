<?php

namespace App\Modules\Inventory\Enums;

class StockTransactionType
{
    public const OPENING_STOCK = 'opening_stock';

    public const PURCHASE = 'purchase';

    public const PURCHASE_RETURN = 'purchase_return';

    public const SALE = 'sale';

    public const SALES_RETURN = 'sales_return';

    public const ADJUSTMENT = 'adjustment';

    public const TRANSFER_IN = 'transfer_in';

    public const TRANSFER_OUT = 'transfer_out';
}