<?php

namespace App\Modules\Accounting\Enums;

class VoucherType
{
    public const SALE = 'sale';

    public const PURCHASE = 'purchase';

    public const CUSTOMER_RECEIPT = 'customer_receipt';

    public const SUPPLIER_PAYMENT = 'supplier_payment';

    public const SALES_RETURN = 'sales_return';

    public const PURCHASE_RETURN = 'purchase_return';

    public const JOURNAL = 'journal';
}