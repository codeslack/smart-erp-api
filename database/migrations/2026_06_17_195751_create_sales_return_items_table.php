<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sales_return_items', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();

            // Tenant
            $table->foreignId('tenant_id')
                ->constrained('tenants')
                ->cascadeOnDelete();

            // Parent
            $table->foreignId('sales_return_id')
                ->constrained('sales_returns')
                ->cascadeOnDelete();

            // Original sale item
            $table->foreignId('sale_item_id')
                ->nullable()
                ->constrained('sale_items')
                ->nullOnDelete();

            // Product & Variant
            $table->foreignId('product_id')
                ->constrained('products')
                ->cascadeOnDelete();

            $table->foreignId('product_variant_id')
                ->nullable()
                ->constrained('product_variants')
                ->restrictOnDelete();

            $table->foreignId('warehouse_id')
                ->constrained('warehouses')
                ->cascadeOnDelete();

            $table->foreignId('unit_id')
                ->nullable()
                ->constrained('units')
                ->nullOnDelete();

            $table->string('line_type', 30)->default('product');
            $table->string('item_name', 255)->nullable();
            $table->string('sku', 100)->nullable();
            $table->text('description')->nullable();

            // Quantities
            $table->decimal('returned_quantity', 18, 4);
            $table->decimal('base_quantity', 18, 4)->nullable();

            // Pricing & cost
            $table->decimal('unit_price', 18, 4);
            $table->decimal('unit_cost', 18, 4)->default(0);

            // Discount
            $table->string('discount_type', 20)->default('fixed');
            $table->decimal('discount_rate', 18, 4)->default(0);
            $table->decimal('discount_amount', 18, 4)->default(0);

            $table->decimal('net_unit_price', 18, 4)->default(0);
            $table->decimal('subtotal', 18, 4)->default(0);

            // Tax
            $table->foreignId('tax_id')
                ->nullable()
                ->constrained('taxes')
                ->nullOnDelete();

            $table->unsignedBigInteger('tax_group_id')->nullable()->index();
            $table->unsignedBigInteger('tax_applicability_rule_id')->nullable()->index();
            $table->json('tax_snapshot')->nullable();
            $table->decimal('tax_rate', 18, 4)->default(0);
            $table->decimal('tax_amount', 18, 4)->default(0);

            $table->decimal('line_total', 18, 4);
            $table->decimal('total_amount', 18, 4)->default(0);

            $table->string('condition', 30)->default('good');
            $table->string('reason', 255)->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index(
                ['tenant_id', 'sales_return_id'],
                'sr_items_tenant_return_index'
            );

            $table->index(
                ['tenant_id', 'product_id'],
                'sr_items_tenant_product_index'
            );

            $table->index(
                ['tenant_id', 'sale_item_id'],
                'sr_items_tenant_sale_item_index'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sales_return_items');
    }
};
