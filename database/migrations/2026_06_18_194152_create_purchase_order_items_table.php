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
        Schema::create('purchase_order_items', function (Blueprint $table) {

            $table->id();

            $table->foreignId('purchase_order_id')
                ->constrained('purchase_orders')
                ->cascadeOnDelete();

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
                'unit_cost',
                18,
                4
            );

            $table->decimal(
                'line_total',
                18,
                4
            );

            $table->decimal(
                'received_quantity',
                15,
                4
            )->default(0);

            $table->decimal(
                'pending_quantity',
                15,
                4
            )->default(0);

            $table->timestamps();

            $table->index([
                'product_id',
                'warehouse_id',
            ]);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('purchase_order_items');
    }
};
