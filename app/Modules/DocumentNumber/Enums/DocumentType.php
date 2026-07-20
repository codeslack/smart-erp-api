<?php

namespace App\Modules\DocumentNumber\Enums;

enum DocumentType: string
{
    case CUSTOMER_RECEIPT = 'customer_receipt';

    case SUPPLIER_PAYMENT = 'supplier_payment';

    case SALE_INVOICE = 'sale_invoice';

    case PURCHASE_INVOICE = 'purchase_invoice';

    case SALES_RETURN = 'sales_return';

    case PURCHASE_RETURN = 'purchase_return';

    case SALES_ORDER = 'sales_order';

    case PURCHASE_ORDER = 'purchase_order';

    case QUOTATION = 'quotation';

    case DELIVERY_NOTE = 'delivery_note';

    case GRN = 'grn';

    case JOURNAL_ENTRY = 'journal_entry';

    case STOCK_ADJUSTMENT = 'stock_adjustment';

    case STOCK_TRANSFER = 'stock_transfer';
}
