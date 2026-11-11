<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sale_items', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();

            // Tenant
            $table->foreignId('tenant_id')
                ->constrained('tenants')
                ->cascadeOnDelete();

            // Parent
            $table->foreignId('sale_id')
                ->constrained('sales')
                ->cascadeOnDelete();

            // Linkage to prior documents
            $table->unsignedBigInteger('sales_order_item_id')->nullable()->index();
            $table->unsignedBigInteger('delivery_note_item_id')->nullable()->index();

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

            // Unit & Quantity
            $table->unsignedBigInteger('unit_id')->nullable()->index();
            $table->decimal('quantity', 18, 4);
            $table->decimal('base_quantity', 18, 4)->nullable();

            // Pricing
            $table->decimal('unit_price', 18, 4);
            $table->decimal('cost_price', 18, 4)->default(0);

            // Discount
            $table->string('discount_type', 20)->default('fixed');
            $table->decimal('discount_rate', 18, 4)->default(0);
            $table->decimal('discount_amount', 18, 4)->default(0);

            $table->decimal('net_unit_price', 18, 4);
            $table->decimal('subtotal', 18, 4);

            // Tax
            $table->unsignedBigInteger('tax_id')->nullable()->index();
            $table->unsignedBigInteger('tax_group_id')->nullable()->index();
            $table->unsignedBigInteger('tax_applicability_rule_id')->nullable()->index();
            $table->json('tax_snapshot')->nullable();
            $table->decimal('tax_rate', 18, 4)->default(0);
            $table->decimal('tax_amount', 18, 4)->default(0);

            // Line totals
            $table->decimal('line_total', 18, 4);
            $table->decimal('total_amount', 18, 4);

            $table->timestamps();
            $table->softDeletes();

            $table->index(
                ['tenant_id', 'product_variant_id'],
                'sale_items_tenant_variant_index'
            );

            $table->index(
                ['sale_id', 'product_id'],
                'sale_items_sale_product_index'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sale_items');
    }
};
