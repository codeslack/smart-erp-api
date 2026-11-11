<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sale_item_serials', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();

            $table->foreignId('tenant_id')
                ->constrained('tenants')->cascadeOnDelete();

            $table->foreignId('sale_id')
                ->constrained('sales')->cascadeOnDelete();

            $table->foreignId('sale_item_id')
                ->constrained('sale_items')->cascadeOnDelete();

            $table->unsignedBigInteger('product_serial_id')->nullable()->index();
            $table->string('serial_number', 150);
            $table->decimal('unit_cost', 18, 4)->default(0);

            $table->timestamps();
            $table->softDeletes();

            $table->index(
                ['tenant_id', 'sale_id'],
                'sale_item_serials_tenant_sale_index'
            );

            $table->index(
                ['tenant_id', 'sale_item_id'],
                'sale_item_serials_tenant_item_index'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sale_item_serials');
    }
};
