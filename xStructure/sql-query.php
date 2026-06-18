<?php

use Illuminate\Support\Facades\DB;

# Product Stocks
DB::select("SELECT * FROM product_stocks ORDER BY warehouse_id");

# Latest Stock Ledger Entries
DB::select("SELECT * FROM stock_ledgers ORDER BY id DESC LIMIT 5");
