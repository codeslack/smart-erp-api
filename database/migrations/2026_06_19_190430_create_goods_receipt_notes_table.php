<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        /*
        |--------------------------------------------------------------------------
        | Goods Receipt Notes
        |--------------------------------------------------------------------------
        */

        Schema::create('goods_receipt_notes', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();

            $table->foreignId('tenant_id')
                ->constrained('tenants')
                ->cascadeOnDelete();

            $table->string('grn_number', 50);
            $table->date('grn_date');

            $table->unsignedBigInteger('purchase_order_id');
            $table->unsignedBigInteger('supplier_id');
            $table->unsignedBigInteger('warehouse_id');

            $table->string(
                'delivery_challan_number',
                100
            )->nullable()->index();

            $table->date(
                'delivery_challan_date'
            )->nullable();

            $table->string(
                'vehicle_number',
                50
            )->nullable();

            $table->string(
                'transporter_name',
                150
            )->nullable();

            $table->string(
                'status',
                30
            )->default('draft')->index();

            $table->text('remarks')->nullable();

            $table->unsignedBigInteger(
                'journal_entry_id'
            )->nullable()->index();

            $table->decimal(
                'total_received_valuation',
                18,
                4
            )->default(0);

            $table->timestamp('confirmed_at')->nullable();
            $table->unsignedBigInteger('confirmed_by')->nullable();

            $table->timestamp('cancelled_at')->nullable();
            $table->unsignedBigInteger('cancelled_by')->nullable();

            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->unique(
                ['tenant_id', 'grn_number'],
                'grn_tenant_number_unique'
            );

            $table->index(
                ['tenant_id', 'purchase_order_id'],
                'grn_tenant_po_index'
            );

            $table->index(
                ['tenant_id', 'supplier_id'],
                'grn_tenant_supplier_index'
            );

            $table->index(
                ['tenant_id', 'warehouse_id'],
                'grn_tenant_warehouse_index'
            );
        });

        /*
        |--------------------------------------------------------------------------
        | Goods Receipt Note Items
        |--------------------------------------------------------------------------
        */

        Schema::create(
            'goods_receipt_note_items',
            function (Blueprint $table) {
                $table->id();
                $table->uuid('uuid')->unique();

                $table->foreignId('tenant_id')
                    ->constrained('tenants')
                    ->cascadeOnDelete();

                $table->unsignedBigInteger(
                    'goods_receipt_note_id'
                );

                $table->unsignedBigInteger(
                    'purchase_order_item_id'
                );

                $table->unsignedBigInteger('product_id');

                $table->foreignId('product_variant_id')
                    ->nullable()
                    ->constrained('product_variants')
                    ->restrictOnDelete();

                $table->unsignedBigInteger('warehouse_id');

                // Transaction UOM.
                $table->unsignedBigInteger('unit_id');

                // Quantity in transaction UOM.
                $table->decimal(
                    'quantity_received',
                    18,
                    4
                );

                // Quantity converted to product base UOM.
                $table->decimal(
                    'base_quantity',
                    18,
                    4
                );

                // Historical cost per transaction UOM.
                $table->decimal(
                    'unit_cost',
                    18,
                    4
                );

                // Historical cost per product base UOM.
                // Used by Inventory V4 for stock valuation and FIFO.
                $table->decimal(
                    'base_unit_cost',
                    18,
                    8
                );

                // Total valuation in transaction currency.
                $table->decimal(
                    'line_total',
                    18,
                    4
                );

                /*
                |--------------------------------------------------------------------------
                | Expected Tax Snapshot
                |--------------------------------------------------------------------------
                |
                | Reference/expected tax from procurement data.
                | This does NOT create Input GST accounting.
                | Actual invoice tax comes from Purchase Bill.
                |
                */

                $table->unsignedBigInteger(
                    'tax_group_id'
                )->nullable();

                $table->decimal(
                    'tax_rate',
                    10,
                    4
                )->default(0);

                $table->decimal(
                    'tax_amount',
                    18,
                    4
                )->default(0);

                $table->text('remarks')->nullable();

                $table->timestamps();
                $table->softDeletes();

                $table->index(
                    ['tenant_id', 'goods_receipt_note_id'],
                    'grn_items_tenant_grn_index'
                );

                $table->index(
                    ['tenant_id', 'purchase_order_item_id'],
                    'grn_items_tenant_po_item_index'
                );

                $table->index(
                    ['tenant_id', 'product_id'],
                    'grn_items_tenant_product_index'
                );

                $table->index(
                    ['tenant_id', 'product_variant_id'],
                    'grn_items_tenant_variant_index'
                );

                $table->index(
                    ['tenant_id', 'warehouse_id'],
                    'grn_items_tenant_warehouse_index'
                );
            }
        );

        /*
        |--------------------------------------------------------------------------
        | GRN Item Batch Receipts
        |--------------------------------------------------------------------------
        */

        Schema::create(
            'goods_receipt_note_item_batches',
            function (Blueprint $table) {
                $table->id();
                $table->uuid('uuid')->unique();

                $table->foreignId('tenant_id')
                    ->constrained('tenants')
                    ->cascadeOnDelete();

                $table->unsignedBigInteger(
                    'goods_receipt_note_item_id'
                );

                $table->string('batch_no', 100);

                $table->decimal(
                    'quantity',
                    18,
                    4
                );

                $table->date('expiry_date')->nullable();

                $table->timestamps();
                $table->softDeletes();

                $table->index(
                    ['tenant_id', 'goods_receipt_note_item_id'],
                    'grn_batches_tenant_item_index'
                );

                $table->index(
                    ['tenant_id', 'batch_no'],
                    'grn_batches_tenant_batch_index'
                );
            }
        );

        /*
        |--------------------------------------------------------------------------
        | GRN Item Serial Receipts
        |--------------------------------------------------------------------------
        */

        Schema::create(
            'goods_receipt_note_item_serials',
            function (Blueprint $table) {
                $table->id();
                $table->uuid('uuid')->unique();

                $table->foreignId('tenant_id')
                    ->constrained('tenants')
                    ->cascadeOnDelete();

                $table->unsignedBigInteger(
                    'goods_receipt_note_item_id'
                );

                $table->string('serial_number', 150);

                $table->timestamps();
                $table->softDeletes();

                $table->index(
                    ['tenant_id', 'goods_receipt_note_item_id'],
                    'grn_serials_tenant_item_index'
                );

                $table->unique(
                    ['tenant_id', 'serial_number'],
                    'grn_serials_tenant_serial_unique'
                );
            }
        );
    }

    public function down(): void
    {
        Schema::dropIfExists(
            'goods_receipt_note_item_serials'
        );

        Schema::dropIfExists(
            'goods_receipt_note_item_batches'
        );

        Schema::dropIfExists(
            'goods_receipt_note_items'
        );

        Schema::dropIfExists(
            'goods_receipt_notes'
        );
    }
};
