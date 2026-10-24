<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('purchase_orders', function (Blueprint $table) {
            $table->id();

            $table->uuid('uuid')->unique();

            // Tenant
            $table->foreignId('tenant_id')
                ->constrained('tenants')
                ->cascadeOnDelete();

            // Document
            $table->string('order_number', 50);
            $table->date('order_date')->index();
            $table->date('expected_delivery_date')->nullable()->index();

            // Supplier & destination
            $table->unsignedBigInteger('supplier_id')->index();
            $table->unsignedBigInteger('warehouse_id')->index();
            $table->unsignedBigInteger('payment_term_id')->nullable()->index();

            // Status
            $table->string('status', 30)->default('draft')->index();

            // Currency
            $table->string('currency_code', 3)->default('INR');
            $table->decimal('exchange_rate', 18, 6)->default(1.000000);

            // Financial summary
            $table->decimal('subtotal_amount', 18, 4)->default(0);
            $table->decimal('discount_amount', 18, 4)->default(0);
            $table->decimal('additional_charge_amount', 18, 4)->default(0);
            $table->decimal('tax_total_amount', 18, 4)->default(0);
            $table->decimal('rounding_adjustment', 18, 4)->default(0);
            $table->decimal('grand_total_amount', 18, 4)->default(0);

            // Notes & terms
            $table->text('notes')->nullable();
            $table->text('terms_and_conditions')->nullable();

            // Audit
            $table->unsignedBigInteger('created_by')->nullable()->index();
            $table->unsignedBigInteger('updated_by')->nullable()->index();

            $table->unsignedBigInteger('confirmed_by')->nullable()->index();
            $table->timestamp('confirmed_at')->nullable();

            $table->unsignedBigInteger('cancelled_by')->nullable()->index();
            $table->timestamp('cancelled_at')->nullable();
            $table->text('cancellation_reason')->nullable();

            $table->timestamps();
            $table->softDeletes();

            // Tenant-scoped document number
            $table->unique([
                'tenant_id',
                'order_number',
            ]);
        });

        Schema::create('purchase_order_items', function (Blueprint $table) {
            $table->id();

            $table->uuid('uuid')->unique();

            // Tenant
            $table->foreignId('tenant_id')
                ->constrained('tenants')
                ->cascadeOnDelete();

            // Parent
            $table->unsignedBigInteger('purchase_order_id')->index();

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
            $table->unsignedBigInteger('unit_of_measure_id')
                ->nullable()
                ->index();

            $table->decimal('quantity', 18, 4);

            // Cached operational quantities
            // Actual source of truth will be GRN / Purchase Bill relations.
            $table->decimal('received_quantity', 18, 4)->default(0);
            $table->decimal('billed_quantity', 18, 4)->default(0);
            $table->decimal('cancelled_quantity', 18, 4)->default(0);

            // Pricing
            $table->decimal('unit_price', 18, 4);

            $table->string('discount_type', 20)->default('fixed');
            $table->decimal('discount_rate', 18, 4)->default(0);
            $table->decimal('discount_amount', 18, 4)->default(0);

            $table->decimal('net_unit_price', 18, 4);
            $table->decimal('subtotal_amount', 18, 4);

            // Tax snapshot
            $table->unsignedBigInteger('tax_group_id')
                ->nullable()
                ->index();

            $table->boolean('is_tax_inclusive')->default(false);

            $table->decimal('tax_base_amount', 18, 4)->default(0);
            $table->decimal('tax_total_amount', 18, 4)->default(0);

            $table->decimal('total_amount', 18, 4);

            $table->timestamps();
            $table->softDeletes();

            // Parent relationship
            $table->foreign('purchase_order_id')
                ->references('id')
                ->on('purchase_orders')
                ->cascadeOnDelete();

            $table->index(
                ['tenant_id', 'product_variant_id'],
                'po_items_tenant_variant_index'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_order_items');
        Schema::dropIfExists('purchase_orders');
    }
};
