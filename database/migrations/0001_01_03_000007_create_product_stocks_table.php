<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_stocks', function (
            Blueprint $table
        ) {
            $table->id();

            $table->uuid('uuid')->unique();

            $table->foreignId('tenant_id')
                ->constrained('tenants')
                ->cascadeOnDelete();

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

            $table->decimal('quantity', 18, 4)->default(0);
            $table->decimal('average_cost', 18, 4)->default(0);

            $table->timestamp('last_movement_at')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->unique(
                [
                    'tenant_id',
                    'product_id',
                    'product_variant_id',
                    'warehouse_id',
                ],
                'product_stocks_unique'
            );

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
                'last_movement_at',
            ]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_stocks');
    }
};
