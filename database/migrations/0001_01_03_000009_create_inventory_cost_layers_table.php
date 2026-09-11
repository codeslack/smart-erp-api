<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventory_cost_layers', function (
            Blueprint $table
        ) {

            $table->id();

            $table->uuid('uuid')
                ->unique();

            /*
            |--------------------------------------------------------------------------
            | Tenant
            |--------------------------------------------------------------------------
            */

            $table->foreignId('tenant_id')
                ->constrained('tenants')
                ->cascadeOnDelete();

            /*
            |--------------------------------------------------------------------------
            | Stock Identity
            |--------------------------------------------------------------------------
            */

            $table->foreignId('product_id')
                ->constrained('products')
                ->cascadeOnDelete();

            $table->foreignId('product_variant_id')
                ->nullable()
                ->constrained('product_variants')
                ->nullOnDelete();

            $table->foreignId('warehouse_id')
                ->constrained('warehouses')
                ->cascadeOnDelete();

            /*
            |--------------------------------------------------------------------------
            | Source Ledger
            |--------------------------------------------------------------------------
            |
            | Every FIFO layer is created from an IN movement.
            |
            */

            $table->foreignId('stock_ledger_id')
                ->constrained('stock_ledgers')
                ->cascadeOnDelete();

            /*
            |--------------------------------------------------------------------------
            | FIFO Layer Quantity
            |--------------------------------------------------------------------------
            */

            $table->decimal(
                'original_quantity',
                18,
                4
            );

            $table->decimal(
                'remaining_quantity',
                18,
                4
            );

            /*
            |--------------------------------------------------------------------------
            | Cost
            |--------------------------------------------------------------------------
            */

            $table->decimal(
                'unit_cost',
                18,
                4
            );

            /*
            |--------------------------------------------------------------------------
            | Layer Date
            |--------------------------------------------------------------------------
            |
            | Used for FIFO ordering.
            |
            */

            $table->dateTime(
                'layer_date'
            );

            /*
            |--------------------------------------------------------------------------
            | Status
            |--------------------------------------------------------------------------
            |
            | OPEN     = remaining quantity exists
            | EXHAUSTED = completely consumed
            |
            */

            $table->string(
                'status',
                20
            )->default('OPEN');

            /*
            |--------------------------------------------------------------------------
            | Audit
            |--------------------------------------------------------------------------
            */

            $table->timestamps();

            /*
            |--------------------------------------------------------------------------
            | Constraints
            |--------------------------------------------------------------------------
            */

            $table->unique(
                [
                    'tenant_id',
                    'stock_ledger_id',
                ],
                'icl_tenant_ledger_unique'
            );

            /*
            |--------------------------------------------------------------------------
            | Indexes
            |--------------------------------------------------------------------------
            */

            $table->index(
                [
                    'tenant_id',
                    'product_id',
                    'warehouse_id',
                ],
                'icl_tenant_product_warehouse_idx'
            );

            $table->index(
                [
                    'tenant_id',
                    'product_variant_id',
                ],
                'icl_tenant_variant_idx'
            );

            $table->index(
                [
                    'tenant_id',
                    'status',
                ],
                'icl_tenant_status_idx'
            );

            /*
            |----------------------------------------------------------------------
            | Critical FIFO Index
            |----------------------------------------------------------------------
            |
            | Oldest OPEN layer must be found quickly.
            |
            */

            $table->index(
                [
                    'tenant_id',
                    'product_id',
                    'product_variant_id',
                    'warehouse_id',
                    'status',
                    'layer_date',
                    'id',
                ],
                'icl_fifo_consumption_idx'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(
            'inventory_cost_layers'
        );
    }
};