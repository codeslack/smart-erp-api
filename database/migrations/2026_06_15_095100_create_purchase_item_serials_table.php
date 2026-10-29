<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('purchase_item_serials', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();

            $table->foreignId('tenant_id')
                ->constrained('tenants')->cascadeOnDelete();

            $table->foreignId('purchase_id')
                ->constrained('purchases')->cascadeOnDelete();

            $table->foreignId('purchase_item_id')
                ->constrained('purchase_items')->cascadeOnDelete();

            $table->string('serial_number', 150);

            $table->timestamps();
            $table->softDeletes();

            $table->index(
                ['tenant_id', 'purchase_id'],
                'purchase_item_serials_tenant_purchase_index'
            );

            $table->index(
                ['tenant_id', 'purchase_item_id'],
                'purchase_item_serials_tenant_item_index'
            );

            $table->unique(
                ['tenant_id', 'purchase_id', 'serial_number'],
                'purchase_item_serials_purchase_serial_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_item_serials');
    }
};
