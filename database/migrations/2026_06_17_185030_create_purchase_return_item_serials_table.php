<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('purchase_return_item_serials', function (Blueprint $table) {
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

            $table->foreignId('product_serial_id')
                ->nullable()
                ->constrained('product_serials')
                ->nullOnDelete();

            $table->string('serial_number', 150);

            $table->timestamps();
            $table->softDeletes();

            $table->unique(
                ['tenant_id', 'purchase_return_id', 'serial_number'],
                'pris_tenant_return_serial_unique'
            );

            $table->index(
                ['tenant_id', 'purchase_return_item_id'],
                'pris_tenant_item_index'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_return_item_serials');
    }
};
