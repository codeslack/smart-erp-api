<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('opening_stock_items', function (Blueprint $table) {
            $table->id();

            $table->uuid('uuid')->unique();

            $table->foreignId('tenant_id')
                ->constrained('tenants')
                ->cascadeOnDelete();

            $table->foreignId('opening_stock_source_id')
                ->constrained('opening_stock_sources')
                ->cascadeOnDelete();

            $table->foreignId('product_id')
                ->constrained('products')
                ->cascadeOnDelete();

            $table->foreignId('product_variant_id')
                ->nullable()
                ->constrained('product_variants')
                ->nullOnDelete();

            $table->foreignId('product_batch_id')
                ->nullable()
                ->constrained('product_batches')
                ->nullOnDelete();

            $table->foreignId('product_serial_id')
                ->nullable()
                ->constrained('product_serials')
                ->nullOnDelete();

            $table->decimal('quantity', 18, 4);

            $table->decimal('unit_cost', 18, 4);

            $table->decimal('total_cost', 18, 4);

            $table->text('remarks')->nullable();

            $table->timestamps();

            $table->index([
                'tenant_id',
                'opening_stock_source_id',
            ]);

            $table->index([
                'tenant_id',
                'product_id',
            ]);

            $table->index([
                'tenant_id',
                'product_variant_id',
            ]);

            $table->index([
                'tenant_id',
                'product_batch_id',
            ]);

            $table->index([
                'tenant_id',
                'product_serial_id',
            ]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('opening_stock_items');
    }
};