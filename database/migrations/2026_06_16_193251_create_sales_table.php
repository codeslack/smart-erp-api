<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sales', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();

            // Tenant
            $table->foreignId('tenant_id')
                ->constrained('tenants')
                ->cascadeOnDelete();

            // Document
            $table->string('sale_no', 50)->index();
            $table->date('sale_date')->index();
            $table->date('due_date')->nullable()->index();
            $table->string('customer_reference_number', 100)->nullable();

            // Customer & References
            $table->foreignId('customer_id')
                ->constrained('customers')
                ->cascadeOnDelete();

            $table->unsignedBigInteger('warehouse_id')->index();
            $table->unsignedBigInteger('sales_order_id')->nullable()->index();
            $table->unsignedBigInteger('delivery_note_id')->nullable()->index();
            $table->unsignedBigInteger('sales_quotation_id')->nullable()->index();
            $table->unsignedBigInteger('payment_term_id')->nullable()->index();

            // Status
            $table->string('status', 30)->default('draft')->index();

            // Currency
            $table->string('currency_code', 3)->default('INR');
            $table->decimal('exchange_rate', 18, 6)->default(1.000000);

            // Financial Summary
            $table->decimal('subtotal', 18, 4)->default(0);
            $table->string('discount_type', 20)->default('fixed');
            $table->decimal('discount_rate', 18, 4)->default(0);
            $table->decimal('discount_amount', 18, 4)->default(0);
            $table->decimal('discount', 18, 4)->default(0);

            $table->decimal('charges_subtotal', 18, 4)->default(0);
            $table->decimal('charges_tax_amount', 18, 4)->default(0);
            $table->decimal('tax_amount', 18, 4)->default(0);
            $table->decimal('tax', 18, 4)->default(0);
            $table->decimal('rounding_amount', 18, 4)->default(0);
            $table->decimal('grand_total', 18, 4)->default(0);

            // Payment tracking
            $table->decimal('paid_amount', 18, 4)->default(0);
            $table->decimal('due_amount', 18, 4)->default(0);

            // Accounting integration
            $table->unsignedBigInteger('journal_entry_id')->nullable()->index();

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

            // Multi-tenant uniqueness
            $table->unique([
                'tenant_id',
                'sale_no',
            ]);

            $table->index([
                'tenant_id',
                'sale_date',
            ]);

            $table->index([
                'tenant_id',
                'customer_id',
            ]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sales');
    }
};
