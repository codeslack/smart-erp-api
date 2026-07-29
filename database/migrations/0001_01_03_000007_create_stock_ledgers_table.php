<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_ledgers', function (Blueprint $table) {

            $table->id();

            $table->uuid('uuid')->unique();

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

            $table->foreignId('warehouse_id')
                ->constrained('warehouses')
                ->cascadeOnDelete();

            /*
            |--------------------------------------------------------------------------
            | Batch Tracking
            |--------------------------------------------------------------------------
            */
            $table->foreignId('product_batch_id')
                ->nullable()
                ->constrained('product_batches')
                ->nullOnDelete();

            /*
            |--------------------------------------------------------------------------
            | Serial Tracking
            |--------------------------------------------------------------------------
            */
            $table->foreignId('product_serial_id')
                ->nullable()
                ->constrained('product_serials')
                ->nullOnDelete();

            /*
            |--------------------------------------------------------------------------
            | Transaction Information
            |--------------------------------------------------------------------------
            */
            $table->string('transaction_type', 50);

            $table->dateTime('transaction_date');

            /*
            |--------------------------------------------------------------------------
            | Reference Information
            |--------------------------------------------------------------------------
            */
            $table->string('reference_type', 100)
                ->nullable();

            $table->unsignedBigInteger('reference_id')
                ->nullable();

            $table->string('reference_no', 100)
                ->nullable();

            /*
            |--------------------------------------------------------------------------
            | Quantity Movement
            |--------------------------------------------------------------------------
            */
            $table->decimal('qty_in', 18, 4)
                ->default(0);

            $table->decimal('qty_out', 18, 4)
                ->default(0);

            /*
            |--------------------------------------------------------------------------
            | Cost Information
            |--------------------------------------------------------------------------
            */
            $table->decimal('unit_cost', 18, 4)
                ->default(0);

            $table->decimal('line_cost', 18, 4)
                ->default(0);

            /*
            |--------------------------------------------------------------------------
            | Running Balance
            |--------------------------------------------------------------------------
            */
            $table->decimal('balance_quantity', 18, 4)
                ->default(0);

            $table->decimal('balance_cost', 18, 4)
                ->default(0);

            /*
            |--------------------------------------------------------------------------
            | Remarks
            |--------------------------------------------------------------------------
            */
            $table->text('remarks')
                ->nullable();

            /*
            |--------------------------------------------------------------------------
            | Audit
            |--------------------------------------------------------------------------
            */
            $table->foreignId('created_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamps();

            /*
            |--------------------------------------------------------------------------
            | Indexes
            |--------------------------------------------------------------------------
            */
            $table->index([
                'tenant_id',
                'product_id',
                'warehouse_id'
            ]);

            $table->index([
                'tenant_id',
                'transaction_type'
            ]);

            $table->index([
                'tenant_id',
                'transaction_date'
            ]);

            $table->index([
                'reference_type',
                'reference_id'
            ]);

            $table->index('product_batch_id');
            $table->index('product_serial_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_ledgers');
    }
};