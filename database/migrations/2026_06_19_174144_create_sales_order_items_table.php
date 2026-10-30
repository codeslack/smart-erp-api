<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sales_order_items', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();

            // Tenant
            $table->foreignId('tenant_id')
                ->constrained('tenants')
                ->cascadeOnDelete();

            // Parent
            $table->foreignId('sales_order_id')
                ->constrained('sales_orders')
                ->cascadeOnDelete();

            // Item
            $table->unsignedBigInteger('product_id')->nullable()->index();

            $table->foreignId('product_variant_id')
                ->nullable()
                ->constrained('product_variants')
                ->restrictOnDelete();

            $table->unsignedBigInteger('warehouse_id')->nullable()->index();

            $table->string('line_type', 30)->default('product');
            $table->string('item_name', 255);
            $table->string('sku', 100)->nullable();
            $table->text('description')->nullable();

            // Unit & quantity
            $table->unsignedBigInteger('unit_of_measure_id')->nullable()->index();
            $table->decimal('quantity', 18, 4);

            // Progress tracking quantities
            $table->decimal('delivered_quantity', 18, 4)->default(0);
            $table->decimal('invoiced_quantity', 18, 4)->default(0);
            $table->decimal('cancelled_quantity', 18, 4)->default(0);

            // Pricing
            $table->decimal('unit_price', 18, 4);
            $table->string('discount_type', 20)->default('fixed');
            $table->decimal('discount_rate', 18, 4)->default(0);
            $table->decimal('discount_amount', 18, 4)->default(0);

            $table->decimal('net_unit_price', 18, 4);
            $table->decimal('subtotal_amount', 18, 4);

            // Tax snapshot
            $table->unsignedBigInteger('tax_group_id')->nullable()->index();
            $table->decimal('tax_rate', 18, 4)->default(0);
            $table->boolean('is_tax_inclusive')->default(false);
            $table->decimal('tax_base_amount', 18, 4)->default(0);
            $table->decimal('tax_total_amount', 18, 4)->default(0);

            $table->decimal('total_amount', 18, 4);

            $table->timestamps();
            $table->softDeletes();

            $table->index(
                ['tenant_id', 'product_variant_id'],
                'so_items_tenant_variant_index'
            );

            $table->index(
                ['sales_order_id', 'product_id'],
                'so_items_order_product_index'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sales_order_items');
    }
};
