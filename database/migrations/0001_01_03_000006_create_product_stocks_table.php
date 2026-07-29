<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_stocks', function (Blueprint $table) {

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
            | Current Stock
            |--------------------------------------------------------------------------
            */
            $table->decimal('quantity', 18, 4)
                ->default(0);

            /*
            |--------------------------------------------------------------------------
            | Costing
            |--------------------------------------------------------------------------
            | WAC = Weighted Average Cost
            */
            $table->decimal('average_cost', 18, 4)
                ->default(0);

            /*
            |--------------------------------------------------------------------------
            | Inventory Valuation
            |--------------------------------------------------------------------------
            | quantity × average_cost
            */
            $table->decimal('inventory_value', 18, 4)
                ->default(0);

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

            /*
            |--------------------------------------------------------------------------
            | Constraints
            |--------------------------------------------------------------------------
            */
            $table->unique(
                [
                    'tenant_id',
                    'product_id',
                    'warehouse_id'
                ],
                'product_stocks_unique'
            );

            /*
            |--------------------------------------------------------------------------
            | Indexes
            |--------------------------------------------------------------------------
            */
            $table->index('tenant_id');
            $table->index('product_id');
            $table->index('warehouse_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_stocks');
    }
};