<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('purchase_return_items', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();

            $table->foreignId('tenant_id')
                ->constrained('tenants')
                ->cascadeOnDelete();

            $table->foreignId('purchase_return_id')
                ->constrained('purchase_returns')
                ->cascadeOnDelete();

            $table->foreignId('purchase_item_id')
                ->nullable()
                ->constrained('purchase_items')
                ->nullOnDelete();

            $table->foreignId('product_id')
                ->constrained('products')
                ->restrictOnDelete();

            $table->foreignId('product_variant_id')
                ->nullable()
                ->constrained('product_variants')
                ->nullOnDelete();

            $table->foreignId('warehouse_id')
                ->constrained('warehouses')
                ->restrictOnDelete();

            $table->foreignId('unit_id')
                ->nullable()
                ->constrained('units')
                ->nullOnDelete();

            $table->decimal('returned_quantity', 18, 4);
            $table->decimal('base_quantity', 18, 4)->nullable();
            $table->decimal('unit_cost', 18, 4);

            $table->decimal('subtotal', 18, 4)->default(0);
            $table->decimal('discount_amount', 18, 4)->default(0);

            $table->unsignedBigInteger('tax_id')->nullable();
            $table->decimal('tax_rate', 8, 4)->default(0);
            $table->decimal('tax_amount', 18, 4)->default(0);
            $table->decimal('line_total', 18, 4)->default(0);

            $table->string('condition', 30)->default('good');
            $table->string('reason', 255)->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index(
                ['tenant_id', 'purchase_return_id'],
                'pri_tenant_return_index'
            );

            $table->index(
                ['tenant_id', 'purchase_item_id'],
                'pri_tenant_item_index'
            );

            $table->index(
                ['tenant_id', 'product_id'],
                'pri_tenant_product_index'
            );

            $table->index(
                ['tenant_id', 'warehouse_id'],
                'pri_tenant_warehouse_index'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_return_items');
    }
};
