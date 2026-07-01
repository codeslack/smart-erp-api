<?php

use Illuminate\Support\Facades\DB;

// For MySQL / MariaDB List of tables
DB::select('SHOW TABLES');

# Product Stocks
DB::select("SELECT * FROM product_stocks ORDER BY warehouse_id");

# Latest Stock Ledger Entries
DB::select("SELECT * FROM stock_ledgers ORDER BY id DESC LIMIT 5");

DB::select("SELECT id, product_id, quantity, received_quantity, pending_quantity FROM purchase_order_items;");
