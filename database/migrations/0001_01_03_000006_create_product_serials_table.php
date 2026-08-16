<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

use App\Modules\Inventory\Enums\ProductSerialStatusEnum;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_serials', function (
            Blueprint $table
        ) {

            $table->id();

            $table->uuid('uuid')
                ->unique();

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
            | Relations
            |--------------------------------------------------------------------------
            */

            $table->foreignId('product_id')
                ->constrained('products')
                ->cascadeOnDelete();

            $table->foreignId('product_variant_id')
                ->nullable()
                ->constrained('product_variants')
                ->nullOnDelete();

            $table->foreignId('warehouse_id')
                ->constrained('warehouses')
                ->cascadeOnDelete();

            $table->foreignId('product_batch_id')
                ->nullable()
                ->constrained('product_batches')
                ->nullOnDelete();

            /*
            |--------------------------------------------------------------------------
            | Serial Information
            |--------------------------------------------------------------------------
            */

            $table->string('serial_number', 150);

            $table->string('imei_number', 150)
                ->nullable();

            /*
            |--------------------------------------------------------------------------
            | Cost Information
            |--------------------------------------------------------------------------
            */

            $table->decimal(
                'purchase_cost',
                18,
                4
            )->default(0);

            /*
            |--------------------------------------------------------------------------
            | Warranty
            |--------------------------------------------------------------------------
            */

            $table->date('warranty_expiry')
                ->nullable();

            /*
            |--------------------------------------------------------------------------
            | Sales Tracking
            |--------------------------------------------------------------------------
            */

            $table->timestamp('sold_at')
                ->nullable();

            /*
            |--------------------------------------------------------------------------
            | Status
            |--------------------------------------------------------------------------
            */

            $table->string('status')
                ->default(
                    ProductSerialStatusEnum::AVAILABLE->value
                );

            /*
            |--------------------------------------------------------------------------
            | Notes
            |--------------------------------------------------------------------------
            */

            $table->text('remarks')
                ->nullable();

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
                ['tenant_id', 'serial_number'],
                'product_serials_serial_unique'
            );

            $table->unique(
                ['tenant_id', 'imei_number'],
                'product_serials_imei_unique'
            );

            /*
            |--------------------------------------------------------------------------
            | Indexes
            |--------------------------------------------------------------------------
            */

            $table->index([
                'tenant_id',
                'product_id'
            ]);

            $table->index([
                'tenant_id',
                'warehouse_id'
            ]);

            $table->index([
                'tenant_id',
                'status'
            ]);

            $table->index([
                'tenant_id',
                'sold_at'
            ]);

            $table->index([
                'tenant_id',
                'warranty_expiry'
            ]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_serials');
    }
};
