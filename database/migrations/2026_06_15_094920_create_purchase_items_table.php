<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('purchase_items', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();

            $table->foreignId('tenant_id')
                ->constrained('tenants')
                ->cascadeOnDelete();

            $table->foreignId('purchase_id')
                ->constrained('purchases')
                ->cascadeOnDelete();

            $table->foreignId('product_id')
                ->constrained('products')->restrictOnDelete();

            $table->foreignId('product_variant_id')
                ->nullable()->constrained('product_variants')->restrictOnDelete();

            $table->foreignId('warehouse_id')
                ->nullable()->constrained('warehouses')->nullOnDelete();

            // Optional source-item references; validate in the service.
            $table->unsignedBigInteger('purchase_order_item_id')->nullable();
            $table->unsignedBigInteger('goods_receipt_note_item_id')->nullable();

            $table->foreignId('unit_id')
                ->nullable()->constrained('units')->nullOnDelete();

            // quantity uses the selected purchase unit.
            // base_quantity uses the product's inventory base unit.
            $table->decimal('quantity', 18, 4);
            $table->decimal('base_quantity', 18, 4)->nullable();

            // Cost per selected purchase unit.
            $table->decimal('unit_cost', 18, 4);
            $table->decimal('base_unit_cost', 18, 8)->nullable();

            // line_total: before item discount.
            // subtotal: after item discount, before tax.
            $table->decimal('line_total', 18, 4)->default(0);
            $table->decimal('subtotal', 18, 4)->default(0);

            // Item-level discount.
            $table->string('discount_type', 20)->default('none');
            $table->decimal('discount_rate', 8, 4)->default(0);
            $table->decimal('discount_amount', 18, 4)->default(0);

            // Tax V4 reference; validate against the actual tax schema.
            $table->unsignedBigInteger('tax_id')->nullable();
            $table->decimal('tax_rate', 8, 4)->default(0);
            $table->decimal('tax_amount', 18, 4)->default(0);

            // Final item total after tax.
            $table->decimal('total_amount', 18, 4)->default(0);

            $table->timestamps();
            $table->softDeletes();

            $table->index(
                ['tenant_id', 'purchase_id'],
                'purchase_items_tenant_purchase_index'
            );

            $table->index(
                ['tenant_id', 'product_id'],
                'purchase_items_tenant_product_index'
            );

            $table->index(
                ['tenant_id', 'product_variant_id'],
                'purchase_items_tenant_variant_index'
            );

            $table->index(
                ['tenant_id', 'warehouse_id'],
                'purchase_items_tenant_warehouse_index'
            );

            $table->index(
                ['tenant_id', 'purchase_order_item_id'],
                'purchase_items_tenant_po_item_index'
            );

            $table->index(
                ['tenant_id', 'goods_receipt_note_item_id'],
                'purchase_items_tenant_grn_item_index'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_items');
    }
};
