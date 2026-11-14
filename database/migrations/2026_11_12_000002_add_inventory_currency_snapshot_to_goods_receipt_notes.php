<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('goods_receipt_notes', function (Blueprint $table) {
            $table->char('inventory_currency_code', 3)
                ->nullable()
                ->after('total_received_valuation');

            $table->decimal('inventory_exchange_rate', 18, 8)
                ->nullable()
                ->after('inventory_currency_code');
        });
    }

    public function down(): void
    {
        Schema::table('goods_receipt_notes', function (Blueprint $table) {
            $table->dropColumn([
                'inventory_currency_code',
                'inventory_exchange_rate',
            ]);
        });
    }
};
