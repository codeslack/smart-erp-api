<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sales_orders', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();

            // Tenant
            $table->foreignId('tenant_id')
                ->constrained('tenants')
                ->cascadeOnDelete();

            // Document
            $table->string('order_number', 50)->index();
            $table->date('order_date')->index();
            $table->date('expected_delivery_date')->nullable()->index();

            // Customer & References
            $table->unsignedBigInteger('customer_id')->index();
            $table->unsignedBigInteger('warehouse_id')->index();
            $table->unsignedBigInteger('payment_term_id')->nullable()->index();
            $table->unsignedBigInteger('sales_quotation_id')->nullable()->index();

            // Status
            $table->string('status', 30)->default('draft')->index();

            // Currency
            $table->string('currency_code', 3)->default('INR');
            $table->decimal('exchange_rate', 18, 6)->default(1.000000);

            // Financial summary
            $table->decimal('subtotal_amount', 18, 4)->default(0);
            $table->decimal('discount_amount', 18, 4)->default(0);
            $table->decimal('additional_charge_amount', 18, 4)->default(0);
            $table->decimal('tax_total_amount', 18, 4)->default(0);
            $table->decimal('rounding_adjustment', 18, 4)->default(0);
            $table->decimal('grand_total_amount', 18, 4)->default(0);

            // Notes & terms
            $table->text('notes')->nullable();
            $table->text('terms_and_conditions')->nullable();

            // Audit
            $table->unsignedBigInteger('created_by')->nullable()->index();
            $table->unsignedBigInteger('updated_by')->nullable()->index();

            $table->unsignedBigInteger('confirmed_by')->nullable()->index();
            $table->timestamp('confirmed_at')->nullable();

            $table->unsignedBigInteger('cancelled_by')->nullable()->index();
            $table->timestamp('cancelled_at')->nullable();
            $table->text('cancellation_reason')->nullable();

            $table->timestamps();
            $table->softDeletes();

            // Tenant-scoped document uniqueness
            $table->unique([
                'tenant_id',
                'order_number',
            ]);

            $table->index([
                'tenant_id',
                'customer_id',
            ]);

            $table->index([
                'tenant_id',
                'order_date',
            ]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sales_orders');
    }
};
