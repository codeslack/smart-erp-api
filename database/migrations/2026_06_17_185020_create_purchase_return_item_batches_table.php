<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('purchase_return_item_batches', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();

            $table->foreignId('tenant_id')
                ->constrained('tenants')
                ->cascadeOnDelete();

            $table->foreignId('purchase_return_id')
                ->constrained('purchase_returns')
                ->cascadeOnDelete();

            $table->foreignId('purchase_return_item_id')
                ->constrained('purchase_return_items')
                ->cascadeOnDelete();

            $table->foreignId('product_batch_id')
                ->nullable()
                ->constrained('product_batches')
                ->nullOnDelete();

            $table->string('batch_no', 100);
            $table->decimal('quantity', 18, 4);

            $table->timestamps();
            $table->softDeletes();

            $table->index(
                ['tenant_id', 'purchase_return_id'],
                'prib_tenant_return_index'
            );

            $table->index(
                ['tenant_id', 'purchase_return_item_id'],
                'prib_tenant_item_index'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_return_item_batches');
    }
};
