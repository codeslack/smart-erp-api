<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('purchase_item_batches', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();

            $table->foreignId('tenant_id')
                ->constrained('tenants')->cascadeOnDelete();

            $table->foreignId('purchase_id')
                ->constrained('purchases')->cascadeOnDelete();

            $table->foreignId('purchase_item_id')
                ->constrained('purchase_items')->cascadeOnDelete();

            $table->string('batch_no', 100);
            $table->decimal('quantity', 18, 4);
            $table->decimal('base_quantity', 18, 4)->nullable();

            $table->date('manufacturing_date')->nullable();
            $table->date('expiry_date')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index(
                ['tenant_id', 'purchase_id'],
                'purchase_item_batches_tenant_purchase_index'
            );

            $table->index(
                ['tenant_id', 'purchase_item_id'],
                'purchase_item_batches_tenant_item_index'
            );

            $table->index(
                ['tenant_id', 'batch_no'],
                'purchase_item_batches_tenant_batch_index'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_item_batches');
    }
};
