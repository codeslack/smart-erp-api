<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('inventory_cost_layer_consumptions', function (
            Blueprint $table
        ) {

            /*
            |--------------------------------------------------------------------------
            | Primary
            |--------------------------------------------------------------------------
            */

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
            | FIFO Layer
            |--------------------------------------------------------------------------
            */

            $table->foreignId('inventory_cost_layer_id');

            $table->foreign(
                'inventory_cost_layer_id',
                'iclc_layer_fk'
            )
                ->references('id')
                ->on('inventory_cost_layers')
                ->restrictOnDelete();

            /*
            |--------------------------------------------------------------------------
            | Stock Ledger
            |--------------------------------------------------------------------------
            |
            | This points to the OUT movement that consumed
            | this FIFO layer.
            |
            */

            $table->foreignId('stock_ledger_id');

            $table->foreign(
                'stock_ledger_id',
                'iclc_ledger_fk'
            )
                ->references('id')
                ->on('stock_ledgers')
                ->restrictOnDelete();

            /*
            |--------------------------------------------------------------------------
            | Consumption
            |--------------------------------------------------------------------------
            */

            $table->decimal(
                'quantity',
                18,
                4
            );

            $table->decimal(
                'unit_cost',
                18,
                4
            );

            $table->decimal(
                'total_cost',
                18,
                4
            );

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

            $table->index(
                [
                    'tenant_id',
                    'inventory_cost_layer_id',
                ],
                'iclc_tenant_layer_idx'
            );

            $table->index(
                [
                    'tenant_id',
                    'stock_ledger_id',
                ],
                'iclc_tenant_ledger_idx'
            );

            /*
            |--------------------------------------------------------------------------
            | Prevent Duplicate Consumption Records
            |--------------------------------------------------------------------------
            |
            | A particular layer can technically be consumed by
            | multiple movements, so stock_ledger_id + layer_id
            | identifies one consumption entry.
            |
            */

            $table->unique(
                [
                    'tenant_id',
                    'inventory_cost_layer_id',
                    'stock_ledger_id',
                ],
                'iclc_tenant_layer_ledger_unique'
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists(
            'inventory_cost_layer_consumptions'
        );
    }
};
