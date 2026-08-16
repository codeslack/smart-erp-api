<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('opening_stock_items', function (
            Blueprint $table
        ) {

            $table->id();

            /*
            |--------------------------------------------------------------------------
            | Header
            |--------------------------------------------------------------------------
            */

            $table->foreignId('opening_stock_id')
                ->constrained('opening_stocks')
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
            |
            | For serialized products an item normally
            | represents one serial.
            |
            */

            $table->foreignId('product_serial_id')
                ->nullable()
                ->constrained('product_serials')
                ->nullOnDelete();

            /*
            |--------------------------------------------------------------------------
            | Quantity
            |--------------------------------------------------------------------------
            */

            $table->decimal(
                'quantity',
                18,
                4
            )->default(0);

            /*
            |--------------------------------------------------------------------------
            | Costing
            |--------------------------------------------------------------------------
            */

            $table->decimal(
                'unit_cost',
                18,
                4
            )->default(0);

            $table->decimal(
                'total_cost',
                18,
                4
            )->default(0);

            /*
            |--------------------------------------------------------------------------
            | Remarks
            |--------------------------------------------------------------------------
            */

            $table->text('remarks')
                ->nullable();

            $table->timestamps();

            /*
            |--------------------------------------------------------------------------
            | Indexes
            |--------------------------------------------------------------------------
            */

            $table->index([
                'opening_stock_id',
                'product_id',
            ]);

            $table->index([
                'product_id',
                'product_variant_id',
            ]);

            $table->index('product_batch_id');

            $table->index('product_serial_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(
            'opening_stock_items'
        );
    }
};
