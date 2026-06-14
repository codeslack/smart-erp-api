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
        Schema::create('products', function (Blueprint $table) {

            $table->id();

            $table->foreignId('tenant_id')
                ->constrained('tenants')
                ->cascadeOnDelete();

            $table->foreignId('category_id')
                ->constrained('categories');

            $table->foreignId('unit_id')
                ->constrained('units');

            $table->foreignId('brand_id')
                ->nullable()
                ->constrained('brands')
                ->nullOnDelete();

            $table->string('sku')->unique();

            $table->string('barcode')
                ->nullable();

            $table->string('name');

            $table->text('description')
                ->nullable();

            $table->decimal(
                'purchase_price',
                15,
                2
            )->default(0);

            $table->decimal(
                'sale_price',
                15,
                2
            )->default(0);

            $table->decimal(
                'minimum_stock',
                15,
                2
            )->default(0);

            $table->boolean('is_active')
                ->default(true);

            $table->timestamps();

            $table->unique([
                'tenant_id',
                'sku'
            ]);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
