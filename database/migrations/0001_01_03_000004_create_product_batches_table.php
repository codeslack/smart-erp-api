<?php

use App\Modules\Product\Enums\BatchStatusEnum;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_batches', function (Blueprint $table) {

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
            | Relations
            |--------------------------------------------------------------------------
            */
            $table->foreignId('product_id')
                ->constrained('products')
                ->cascadeOnDelete();

            $table->foreignId('warehouse_id')
                ->constrained('warehouses')
                ->cascadeOnDelete();

            /*
            |--------------------------------------------------------------------------
            | Batch Information
            |--------------------------------------------------------------------------
            */
            $table->string('batch_no', 100);

            $table->date('manufacturing_date')
                ->nullable();

            $table->date('expiry_date')
                ->nullable();

            /*
            |--------------------------------------------------------------------------
            | Cost Information
            |--------------------------------------------------------------------------
            */
            $table->decimal('unit_cost', 18, 4)
                ->default(0);

            /*
            |--------------------------------------------------------------------------
            | Quantity Tracking
            |--------------------------------------------------------------------------
            */
            $table->decimal('original_quantity', 18, 4)
                ->default(0);

            $table->decimal('remaining_quantity', 18, 4)
                ->default(0);

            /*
            |--------------------------------------------------------------------------
            | Batch Status
            |--------------------------------------------------------------------------
            */
            $table->string('status')
                ->default(
                    BatchStatusEnum::ACTIVE->value
                );

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
                [
                    'tenant_id',
                    'product_id',
                    'warehouse_id',
                    'batch_no'
                ],
                'product_batches_unique_batch'
            );

            /*
            |--------------------------------------------------------------------------
            | Indexes
            |--------------------------------------------------------------------------
            */
            $table->index('tenant_id');
            $table->index('product_id');
            $table->index('warehouse_id');
            $table->index('batch_no');
            $table->index('expiry_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_batches');
    }
};