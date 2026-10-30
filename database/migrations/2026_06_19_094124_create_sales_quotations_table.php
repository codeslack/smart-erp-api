<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sales_quotations', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();

            $table->foreignId('tenant_id')
                ->constrained('tenants')
                ->cascadeOnDelete();

            $table->foreignId('customer_id')
                ->constrained('customers')
                ->cascadeOnDelete();

            $table->foreignId('warehouse_id')
                ->nullable()
                ->constrained('warehouses')
                ->nullOnDelete();

            $table->string('quotation_no', 50);
            $table->date('quotation_date');
            $table->date('expiry_date')->nullable();

            $table->string('currency_code', 10)->default('USD');
            $table->decimal('exchange_rate', 18, 6)->default(1.000000);

            $table->decimal('subtotal', 18, 4)->default(0);
            $table->decimal('discount_amount', 18, 4)->default(0);
            $table->decimal('tax_amount', 18, 4)->default(0);
            $table->decimal('shipping_amount', 18, 4)->default(0);
            $table->decimal('rounding_amount', 18, 4)->default(0);
            $table->decimal('grand_total', 18, 4)->default(0);

            $table->string('status', 30)->default('draft');
            $table->text('notes')->nullable();
            $table->text('terms_conditions')->nullable();

            $table->unsignedBigInteger('converted_sales_order_id')->nullable()->index();
            $table->unsignedBigInteger('converted_sale_id')->nullable()->index();

            $table->timestamp('sent_at')->nullable();
            $table->timestamp('accepted_at')->nullable();
            $table->timestamp('rejected_at')->nullable();
            $table->string('rejection_reason', 255)->nullable();

            $table->foreignId('created_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->foreignId('updated_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->softDeletes();
            $table->timestamps();

            $table->unique(['tenant_id', 'quotation_no']);
            $table->index(['tenant_id', 'customer_id']);
            $table->index(['tenant_id', 'status']);
            $table->index(['tenant_id', 'quotation_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sales_quotations');
    }
};
