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
        Schema::create('product_variants', function (
            Blueprint $table
        ) {

            $table->id();

            $table->uuid('uuid')
                ->unique();

            $table->foreignId('tenant_id')
                ->constrained('tenants')
                ->cascadeOnDelete();

            $table->foreignId('product_id')
                ->constrained('products')
                ->cascadeOnDelete();

            /*
            |--------------------------------------------------------------------------
            | Identity
            |--------------------------------------------------------------------------
            */

            $table->string('code', 50);

            $table->string('sku', 100)
                ->nullable();

            $table->string('barcode', 100)
                ->nullable();

            $table->string('name');

            /*
            |--------------------------------------------------------------------------
            | Pricing Override
            |--------------------------------------------------------------------------
            */

            $table->decimal(
                'purchase_price',
                18,
                4
            )->nullable();

            $table->decimal(
                'selling_price',
                18,
                4
            )->nullable();

            /*
            |--------------------------------------------------------------------------
            | Status
            |--------------------------------------------------------------------------
            */

            $table->boolean('is_active')
                ->default(true);

            $table->timestamps();

            $table->softDeletes();

            $table->unique([
                'tenant_id',
                'code'
            ]);

            $table->unique([
                'tenant_id',
                'sku'
            ]);

            $table->index([
                'tenant_id',
                'product_id'
            ]);

            $table->index('sku');
            
            $table->index('barcode');
        });

        Schema::create('product_variant_attributes', function (Blueprint $table) {

            $table->id();

            $table->foreignId('product_variant_id')
                ->constrained('product_variants')
                ->cascadeOnDelete();

            $table->string('attribute_name');

            $table->string('attribute_value');

            $table->timestamps();

            $table->index(
                [
                    'product_variant_id',
                    'attribute_name'
                ],
                'p_variant_attr_id_name_idx'
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('product_variant_attributes');
        Schema::dropIfExists('product_variants');
    }
};
