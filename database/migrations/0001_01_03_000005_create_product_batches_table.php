<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_batches', function (
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
            | Product
            |--------------------------------------------------------------------------
            */

            $table->foreignId('product_id')
                ->constrained('products')
                ->cascadeOnDelete();

            $table->foreignId('product_variant_id')
                ->nullable()
                ->constrained('product_variants')
                ->nullOnDelete();

            /*
            |--------------------------------------------------------------------------
            | Warehouse
            |--------------------------------------------------------------------------
            */

            $table->foreignId('warehouse_id')
                ->constrained('warehouses')
                ->cascadeOnDelete();

            /*
            |--------------------------------------------------------------------------
            | Source Document
            |--------------------------------------------------------------------------
            |
            | Opening Stock
            | Purchase
            | Production
            | Stock Adjustment
            |
            */

            $table->nullableMorphs(
                'sourceable'
            );

            /*
            |--------------------------------------------------------------------------
            | Batch Information
            |--------------------------------------------------------------------------
            */

            $table->string(
                'batch_no',
                100
            );

            $table->date(
                'manufacturing_date'
            )->nullable();

            $table->dateTime(
                'received_at'
            )->nullable();

            $table->date(
                'expiry_date'
            )->nullable();

            /*
            |--------------------------------------------------------------------------
            | Cost Information
            |--------------------------------------------------------------------------
            */

            $table->decimal(
                'unit_cost',
                18,
                4
            )->default(0);

            /*
            |--------------------------------------------------------------------------
            | Quantity Tracking
            |--------------------------------------------------------------------------
            */

            $table->decimal(
                'original_quantity',
                18,
                4
            )->default(0);

            $table->decimal(
                'remaining_quantity',
                18,
                4
            )->default(0);

            /*
            |--------------------------------------------------------------------------
            | Notes
            |--------------------------------------------------------------------------
            */

            $table->text('remarks')
                ->nullable();

            /*
            |--------------------------------------------------------------------------
            | Status
            |--------------------------------------------------------------------------
            */

            $table->boolean('is_active')
                ->default(true);

            /*
            |--------------------------------------------------------------------------
            | Audit
            |--------------------------------------------------------------------------
            */

            $table->foreignId('created_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->foreignId('updated_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamps();

            $table->softDeletes();

            /*
            |--------------------------------------------------------------------------
            | Constraints
            |--------------------------------------------------------------------------
            */

            $table->unique(
                [
                    'tenant_id',
                    'warehouse_id',
                    'batch_no',
                ],
                'product_batches_unique_batch'
            );

            /*
            |--------------------------------------------------------------------------
            | Indexes
            |--------------------------------------------------------------------------
            */

            $table->index([
                'tenant_id',
                'product_id',
            ]);

            $table->index([
                'tenant_id',
                'warehouse_id',
            ]);

            $table->index([
                'tenant_id',
                'expiry_date',
            ]);

            $table->index('batch_no');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(
            'product_batches'
        );
    }
};
