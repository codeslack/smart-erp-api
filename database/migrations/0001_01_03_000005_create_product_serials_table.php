<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use App\Modules\Product\Enums\SerialStatusEnum;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_serials', function (Blueprint $table) {

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
            $table->decimal('purchase_cost', 18, 4)
                ->default(0);

            /*
            |--------------------------------------------------------------------------
            | Warranty
            |--------------------------------------------------------------------------
            */
            $table->date('warranty_expiry')
                ->nullable();

            /*
            |--------------------------------------------------------------------------
            | Current Status
            |--------------------------------------------------------------------------
            */
            $table->string('status')
                ->default(
                    SerialStatusEnum::AVAILABLE->value
                );

            /*
            |--------------------------------------------------------------------------
            | Reference Tracking
            |--------------------------------------------------------------------------
            | Future use:
            | Sale
            | Return
            | Transfer
            */
            $table->string('current_document_type')
                ->nullable();

            $table->unsignedBigInteger('current_document_id')
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
            $table->index('tenant_id');
            $table->index('product_id');
            $table->index('warehouse_id');
            $table->index('status');
            $table->index([
                'current_document_type',
                'current_document_id'
            ]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_serials');
    }
};