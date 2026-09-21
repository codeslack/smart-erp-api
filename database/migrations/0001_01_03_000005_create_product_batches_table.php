<?php

use App\Modules\Inventory\Enums\ProductBatchStatusEnum;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_batches', function (
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

            /*
            | Source document
            */
            $table->nullableMorphs('sourceable');

            /*
            | Batch identity
            */
            $table->string('batch_no', 100);

            $table->date('manufacturing_date')
                ->nullable();

            $table->date('expiry_date')
                ->nullable();

            $table->dateTime('received_at')
                ->nullable();

            /*
            | Cost
            */
            $table->decimal(
                'unit_cost',
                18,
                4
            )->default(0);

            /*
            | Batch quantity
            |
            | original_quantity = quantity initially received
            | remaining_quantity = current quantity remaining
            */
            $table->decimal(
                'original_quantity',
                18,
                4
            )->default(0);

            $table->decimal(
                'remaining_quantity',
                18,
                4
            )->default(0);

            $table->text('remarks')
                ->nullable();

            $table->string(
                'status',
                30
            )->default(
                ProductBatchStatusEnum::DRAFT->value
            );

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
            | Batch identity
            */
            $table->unique([
                'tenant_id',
                'product_id',
                'warehouse_id',
                'batch_no',
            ], 'product_batches_unique_batch');

            /*
            | Indexes
            */
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
                'expiry_date',
            ]);

            $table->index([
                'tenant_id',
                'batch_no',
            ]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_batches');
    }
};