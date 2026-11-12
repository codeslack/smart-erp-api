<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('purchase_returns', function (Blueprint $table) {
            $table->string('currency_code', 3)->default('INR');
            $table->decimal('exchange_rate', 18, 8)->default(1.00000000);
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
