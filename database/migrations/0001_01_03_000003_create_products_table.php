<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use App\Modules\Product\Enums\ProductTypeEnum;
use App\Modules\Product\Enums\ProductStatusEnum;
use App\Modules\Product\Enums\InventoryTrackingTypeEnum;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {

            $table->id();

            $table->uuid('uuid')->unique();

            /*
            |--------------------------------------------------------------------------
            | Tenant
            |--------------------------------------------------------------------------
            */
            $table->foreignId('tenant_id')
                ->constrained('tenants')
                ->cascadeOnDelete();

            /*
            |--------------------------------------------------------------------------
            | Master Relations
            |--------------------------------------------------------------------------
            */
            $table->foreignId('category_id')
                ->nullable()
                ->constrained('categories')
                ->nullOnDelete();

            $table->foreignId('brand_id')
                ->nullable()
                ->constrained('brands')
                ->nullOnDelete();

            $table->foreignId('unit_id')
                ->nullable()
                ->constrained('units')
                ->nullOnDelete();

            /*
            |--------------------------------------------------------------------------
            | Product Identity
            |--------------------------------------------------------------------------
            */
            $table->string('code', 50);

            $table->string('sku', 100)
                ->nullable();

            $table->string('barcode', 100)
                ->nullable();

            $table->string('name');

            $table->string('slug');

            $table->text('description')
                ->nullable();

            /*
            |--------------------------------------------------------------------------
            | Product Classification
            |--------------------------------------------------------------------------
            */
            $table->string('product_type')
                ->default(ProductTypeEnum::GENERAL->value);

            $table->string('inventory_tracking_type')
                ->default(InventoryTrackingTypeEnum::NORMAL->value);

            /*
            |--------------------------------------------------------------------------
            | Inventory Behaviour
            |--------------------------------------------------------------------------
            */
            $table->boolean('track_inventory')
                ->default(true);

            $table->boolean('track_batch')
                ->default(false);

            $table->boolean('track_serial')
                ->default(false);

            $table->boolean('has_expiry')
                ->default(false);

            $table->boolean('has_warranty')
                ->default(false);

            $table->boolean('requires_prescription')
                ->default(false);

            /*
            |--------------------------------------------------------------------------
            | Pricing
            |--------------------------------------------------------------------------
            */
            $table->decimal('purchase_price', 18, 4)
                ->default(0);

            $table->decimal('selling_price', 18, 4)
                ->default(0);

            /*
            |--------------------------------------------------------------------------
            | Stock Control
            |--------------------------------------------------------------------------
            */
            $table->decimal('minimum_stock', 18, 4)
                ->default(0);

            $table->decimal('maximum_stock', 18, 4)
                ->default(0);

            $table->decimal('reorder_level', 18, 4)
                ->default(0);

            $table->decimal('critical_level', 18, 4)
                ->default(0);

            /*
            |--------------------------------------------------------------------------
            | Accounting Mapping
            |--------------------------------------------------------------------------
            */
            $table->unsignedBigInteger('inventory_account_id')
                ->nullable();

            $table->unsignedBigInteger('purchase_account_id')
                ->nullable();

            $table->unsignedBigInteger('sales_account_id')
                ->nullable();

            $table->unsignedBigInteger('cogs_account_id')
                ->nullable();

            /*
            |--------------------------------------------------------------------------
            | Status
            |--------------------------------------------------------------------------
            */
            $table->string('status')
                ->default(ProductStatusEnum::ACTIVE->value);

            /*
            |--------------------------------------------------------------------------
            | Audit
            |--------------------------------------------------------------------------
            */
            $table->foreignId('created_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->foreignId('updated_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamps();
            $table->softDeletes();

            /*
            |--------------------------------------------------------------------------
            | Constraints
            |--------------------------------------------------------------------------
            */
            $table->unique(
                ['tenant_id', 'code'],
                'products_tenant_code_unique'
            );

            $table->unique(
                ['tenant_id', 'slug'],
                'products_tenant_slug_unique'
            );

            $table->unique(
                ['tenant_id', 'sku'],
                'products_tenant_sku_unique'
            );

            $table->unique(
                ['tenant_id', 'barcode'],
                'products_tenant_barcode_unique'
            );

            /*
            |--------------------------------------------------------------------------
            | Indexes
            |--------------------------------------------------------------------------
            */
            $table->index('tenant_id');
            $table->index('name');
            $table->index('status');
            $table->index('product_type');
            $table->index('inventory_tracking_type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};