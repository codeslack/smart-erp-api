<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sale_items', function (
            Blueprint $table
        ) {
            $table->id();

            $table->foreignId('sale_id')
                ->constrained('sales')
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
                'unit_price',
                18,
                4
            );

            $table->decimal(
                'cost_price',
                18,
                4
            )->default(0);

            $table->decimal(
                'line_total',
                18,
                4
            );

            $table->timestamps();

            $table->index([
                'sale_id',
                'product_id'
            ]);

            $table->index([
                'product_id',
                'warehouse_id'
            ]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(
            'sale_items'
        );
    }
};
