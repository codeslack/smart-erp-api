<?php

namespace App\Modules\DocumentNumber\Enums;

enum DocumentType: string
{
    case OPENING_STOCK = 'opening_stock';

    case RECEIPT = 'receipt';
    case PAYMENT = 'payment';

    case SALE_INVOICE = 'sale_invoice';
    case PURCHASE_INVOICE = 'purchase_invoice';

    case SALES_RETURN = 'sales_return';
    case PURCHASE_RETURN = 'purchase_return';

    case SALES_ORDER = 'sales_order';
    case PURCHASE_ORDER = 'purchase_order';

    case QUOTATION = 'quotation';
    case DELIVERY_NOTE = 'delivery_note';

    case GOODS_RECEIPT_NOTE = 'goods_receipt_note';

    case JOURNAL_ENTRY = 'journal_entry';

    case STOCK_ADJUSTMENT = 'stock_adjustment';
    case STOCK_TRANSFER = 'stock_transfer';

    public function prefix(): string
    {
        return match ($this) {
            self::OPENING_STOCK => 'OPN',

            self::RECEIPT => 'REC',
            self::PAYMENT => 'PAY',

            self::SALE_INVOICE => 'SAL',
            self::PURCHASE_INVOICE => 'PUR',

            self::SALES_RETURN => 'SRET',
            self::PURCHASE_RETURN => 'PRET',

            self::SALES_ORDER => 'SO',
            self::PURCHASE_ORDER => 'PO',

            self::QUOTATION => 'QUO',
            self::DELIVERY_NOTE => 'DN',

            self::GOODS_RECEIPT_NOTE => 'GRN',

            self::JOURNAL_ENTRY => 'JRN',

            self::STOCK_ADJUSTMENT => 'ADJ',
            self::STOCK_TRANSFER => 'STRF',
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::OPENING_STOCK => 'Opening Stock',

            self::RECEIPT => 'Receipt',
            self::PAYMENT => 'Payment',

            self::SALE_INVOICE => 'Sale Invoice',
            self::PURCHASE_INVOICE => 'Purchase Invoice',

            self::SALES_RETURN => 'Sales Return',
            self::PURCHASE_RETURN => 'Purchase Return',

            self::SALES_ORDER => 'Sales Order',
            self::PURCHASE_ORDER => 'Purchase Order',

            self::QUOTATION => 'Quotation',
            self::DELIVERY_NOTE => 'Delivery Note',

            self::GOODS_RECEIPT_NOTE => 'Goods Receipt Note',

            self::JOURNAL_ENTRY => 'Journal Entry',

            self::STOCK_ADJUSTMENT => 'Stock Adjustment',
            self::STOCK_TRANSFER => 'Stock Transfer',
        };
    }
}