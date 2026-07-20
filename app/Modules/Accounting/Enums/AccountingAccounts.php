<?php

namespace App\Modules\Accounting\Enums;

class AccountingAccounts
{
    /*
    |--------------------------------------------------------------------------
    | Assets
    |--------------------------------------------------------------------------
    */

    public const CASH = '1000';

    public const BANK = '1010';

    public const ACCOUNTS_RECEIVABLE = '1100';

    public const SUPPLIER_ADVANCES = '1110';

    public const INVENTORY = '1200';

    public const INPUT_TAX_RECEIVABLE = '1210';

    /*
    |--------------------------------------------------------------------------
    | Liabilities
    |--------------------------------------------------------------------------
    */

    public const ACCOUNTS_PAYABLE = '2000';

    public const CUSTOMER_ADVANCES = '2010';

    public const OUTPUT_TAX_PAYABLE = '2020';

    /*
    |--------------------------------------------------------------------------
    | Equity
    |--------------------------------------------------------------------------
    */

    public const OWNER_EQUITY = '3000';

    /*
    |--------------------------------------------------------------------------
    | Income
    |--------------------------------------------------------------------------
    */

    public const SALES_REVENUE = '4000';

    public const SALES_RETURN = '4100';

    /*
    |--------------------------------------------------------------------------
    | Expenses
    |--------------------------------------------------------------------------
    */

    public const COST_OF_GOODS_SOLD = '5000';

    public const PURCHASE_FREIGHT = '5100';

    public const PURCHASE_HANDLING = '5200';
}