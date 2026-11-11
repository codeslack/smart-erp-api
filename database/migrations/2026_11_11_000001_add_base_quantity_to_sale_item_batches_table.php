<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sale_item_batches', function (Blueprint $table) {
            $table->decimal('base_quantity', 18, 4)
                ->nullable()
                ->after('quantity');

            $table->decimal('unit_cost', 18, 4)
                ->default(0)
                ->after('base_quantity');
        });
    }

    public function down(): void
    {
        Schema::table('sale_item_batches', function (Blueprint $table) {
            $table->dropColumn(['base_quantity', 'unit_cost']);
        });
    }
};
