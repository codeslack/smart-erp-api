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
        Schema::create('stock_ledgers', function (Blueprint $table) {

            $table->id();

            $table->foreignId('tenant_id')
                ->constrained('tenants')
                ->cascadeOnDelete();

            $table->foreignId('product_id')
                ->constrained('products')
                ->cascadeOnDelete();

            $table->foreignId('warehouse_id')
                ->constrained('warehouses')
                ->cascadeOnDelete();

            $table->string('transaction_type');

            $table->string('reference_type')
                ->nullable();

            $table->unsignedBigInteger('reference_id')
                ->nullable();

            $table->decimal('qty_in', 18, 4)
                ->default(0);

            $table->decimal('qty_out', 18, 4)
                ->default(0);

            $table->decimal('balance_after', 18, 4)
                ->default(0);

            $table->text('remarks')
                ->nullable();

            $table->timestamps();

            $table->index([
                'tenant_id',
                'product_id'
            ]);

            $table->index([
                'tenant_id',
                'warehouse_id'
            ]);

            $table->index([
                'transaction_type'
            ]);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('stock_ledgers');
    }
};
