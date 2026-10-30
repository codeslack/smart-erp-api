<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sales_returns', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();

            // Tenant
            $table->foreignId('tenant_id')
                ->constrained('tenants')
                ->cascadeOnDelete();

            // Invoiced Sale Link
            $table->foreignId('sale_id')
                ->constrained('sales')
                ->restrictOnDelete();

            // Customer
            $table->foreignId('customer_id')
                ->constrained('customers')
                ->restrictOnDelete();

            $table->unsignedBigInteger('warehouse_id')->nullable()->index();

            // Document
            $table->string('return_no', 50)->index();
            $table->date('return_date')->index();

            // Currency
            $table->string('currency_code', 3)->default('INR');
            $table->decimal('exchange_rate', 18, 6)->default(1.000000);

            // Financial Summary
            $table->decimal('subtotal', 18, 4)->default(0);
            $table->decimal('discount_amount', 18, 4)->default(0);
            $table->decimal('tax_amount', 18, 4)->default(0);
            $table->decimal('rounding_amount', 18, 4)->default(0);
            $table->decimal('grand_total', 18, 4)->default(0);

            // Refund details
            $table->decimal('refund_amount', 18, 4)->default(0);
            $table->decimal('credited_amount', 18, 4)->default(0);
            $table->string('refund_type', 30)->default('credit_note');

            $table->foreignId('refund_account_id')
                ->nullable()
                ->constrained('chart_of_accounts')
                ->nullOnDelete();

            $table->string('return_reason', 255)->nullable();
            $table->string('status', 30)->default('draft')->index();

            // Accounting link
            $table->unsignedBigInteger('journal_entry_id')->nullable()->index();

            // Audit
            $table->foreignId('created_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->foreignId('updated_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->foreignId('approved_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamp('approved_at')->nullable();

            $table->foreignId('cancelled_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamp('cancelled_at')->nullable();
            $table->text('cancellation_reason')->nullable();

            $table->text('notes')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->unique(
                ['tenant_id', 'return_no'],
                'sr_tenant_return_no_unique'
            );

            $table->index(
                ['tenant_id', 'return_date'],
                'sr_tenant_date_index'
            );

            $table->index(
                ['tenant_id', 'customer_id'],
                'sr_tenant_customer_index'
            );

            $table->index(
                ['tenant_id', 'sale_id'],
                'sr_tenant_sale_index'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sales_returns');
    }
};
