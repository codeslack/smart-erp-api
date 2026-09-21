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

            $table->uuid('uuid')->unique();

            $table->foreignId('tenant_id')
                ->constrained('tenants')
                ->cascadeOnDelete();

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
            | Source document
            */
            $table->nullableMorphs('sourceable');

            /*
            | Serial identity
            */
            $table->string(
                'serial_number',
                150
            );

            $table->string(
                'imei_number',
                150
            )->nullable();

            /*
            | Cost
            */
            $table->decimal(
                'purchase_cost',
                18,
                4
            )->default(0);

            /*
            | Warranty
            */
            $table->date(
                'warranty_expiry'
            )->nullable();

            /*
            | Lifecycle
            */
            $table->string(
                'status',
                30
            )->default(
                ProductSerialStatusEnum::DRAFT->value
            );

            $table->timestamp('sold_at')
                ->nullable();

            $table->text('remarks')
                ->nullable();

            /*
            | Audit
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
            | Serial must be unique per tenant.
            */
            $table->unique([
                'tenant_id',
                'serial_number',
            ], 'product_serials_serial_unique');

            /*
            | IMEI must be unique when supplied.
            */
            $table->unique([
                'tenant_id',
                'imei_number',
            ], 'product_serials_imei_unique');

            $table->index([
                'tenant_id',
                'product_id',
            ]);

            $table->index([
                'tenant_id',
                'warehouse_id',
            ]);

            $table->index([
                'tenant_id',
                'status',
            ]);

            $table->index([
                'tenant_id',
                'product_batch_id',
            ]);

            $table->index([
                'tenant_id',
                'warranty_expiry',
            ]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_serials');
    }
};