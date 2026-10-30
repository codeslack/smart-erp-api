<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sale_return_item_serials', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();

            $table->foreignId('tenant_id')
                ->constrained('tenants')->cascadeOnDelete();

            $table->foreignId('sales_return_id')
                ->constrained('sales_returns')->cascadeOnDelete();

            $table->foreignId('sales_return_item_id')
                ->constrained('sales_return_items')->cascadeOnDelete();

            $table->unsignedBigInteger('product_serial_id')->nullable()->index();
            $table->string('serial_number', 150);

            $table->timestamps();
            $table->softDeletes();

            $table->index(
                ['tenant_id', 'sales_return_id'],
                'sr_item_serials_tenant_return_index'
            );

            $table->index(
                ['tenant_id', 'sales_return_item_id'],
                'sr_item_serials_tenant_item_index'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sale_return_item_serials');
    }
};
