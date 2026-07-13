<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('sales_return_items', function (Blueprint $table) {

            $table->id();

            $table->foreignId('sales_return_id')
                ->constrained('sales_returns')
                ->cascadeOnDelete();

            $table->foreignId('sale_item_id')
                ->nullable()
                ->constrained('sale_items')
                ->nullOnDelete();

            $table->foreignId('product_id')
                ->constrained('products')
                ->cascadeOnDelete();

            $table->foreignId('warehouse_id')
                ->constrained('warehouses')
                ->cascadeOnDelete();

            $table->decimal(
                'quantity',
                18,
                4
            );

            $table->decimal(
                'unit_price',
                18,
                4
            );

            $table->decimal(
                'discount',
                18,
                4
            )->default(0);

            $table->decimal(
                'tax',
                18,
                4
            )->default(0);

            $table->decimal(
                'line_total',
                18,
                4
            );

            $table->string('condition')
                ->default('good');

            $table->string('reason')
                ->nullable();

            $table->timestamps();

            $table->index('sales_return_id');

            $table->index('sale_item_id');

            $table->index('product_id');

            $table->index('warehouse_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sales_return_items');
    }
};
