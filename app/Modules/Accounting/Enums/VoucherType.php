<?php

namespace App\Modules\Accounting\Enums;

class VoucherType
{
    public const SALE = 'sale';

    public const PURCHASE = 'purchase';

    public const RECEIPT = 'receipt';

    public const PAYMENT = 'payment';

    public const SALES_RETURN = 'sales_return';

    public const PURCHASE_RETURN = 'purchase_return';

    public const JOURNAL = 'journal';

    public const OPENING_STOCK = 'opening_stock';

    // so, i am fixed 1st AccountingAccounts, VoucherType
}
