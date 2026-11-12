<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('purchase_returns', function (Blueprint $table) {
            $table->string('currency_code', 3)->default('INR');
            $table->decimal('exchange_rate', 18, 8)->default(1.00000000);
        });

        // Preserve the original purchase currency for return documents
        // that already exist when this migration is applied.
        DB::table('purchase_returns')
            ->join(
                'purchases',
                'purchase_returns.purchase_id',
                '=',
                'purchases.id'
            )
            ->update([
                'purchase_returns.currency_code' => DB::raw('purchases.currency_code'),
                'purchase_returns.exchange_rate' => DB::raw('purchases.exchange_rate'),
            ]);
    }

    public function down(): void
    {
        Schema::table('purchase_returns', function (Blueprint $table) {
            $table->dropColumn([
                'currency_code',
                'exchange_rate',
            ]);
        });
    }
};
