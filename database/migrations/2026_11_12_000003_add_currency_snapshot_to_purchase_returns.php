<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('purchase_returns', function (Blueprint $table) {
            $table->char('currency_code', 3)
                ->nullable()
                ->after('return_date');

            $table->decimal('exchange_rate', 18, 8)
                ->nullable()
                ->after('currency_code');
        });
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
