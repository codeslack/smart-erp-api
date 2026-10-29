<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('purchases', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();

            $table->foreignId('tenant_id')
                ->constrained('tenants')
                ->cascadeOnDelete();

            $table->foreignId('supplier_id')
                ->constrained('suppliers')
                ->restrictOnDelete();

            $table->foreignId('warehouse_id')
                ->nullable()
                ->constrained('warehouses')
                ->nullOnDelete();

            // Optional source documents; validate tenant ownership in services.
            $table->unsignedBigInteger('purchase_order_id')->nullable();
            $table->unsignedBigInteger('goods_receipt_note_id')->nullable();

            $table->foreignId('payment_term_id')
                ->nullable()
                ->constrained('payment_terms')
                ->nullOnDelete();

            $table->string('purchase_no', 50);
            $table->string('supplier_invoice_number', 100)->nullable();
            $table->date('supplier_invoice_date')->nullable();

            $table->date('purchase_date');
            $table->date('due_date')->nullable();

            $table->string('currency_code', 3)->default('INR');
            $table->decimal('exchange_rate', 18, 6)->default(1.000000);

            // exclusive = tax added on top; inclusive = tax included in price.
            $table->string('tax_calculation_mode', 20)->default('exclusive');

            $table->decimal('subtotal', 18, 4)->default(0);

            // Whole-document discount.
            $table->string('discount_type', 20)->default('none');
            $table->decimal('discount_rate', 8, 4)->default(0);
            $table->decimal('discount_amount', 18, 4)->default(0);

            $table->decimal('charges_subtotal', 18, 4)->default(0);
            $table->decimal('charges_tax_amount', 18, 4)->default(0);
            $table->decimal('tax_amount', 18, 4)->default(0);
            $table->decimal('rounding_amount', 18, 4)->default(0);

            $table->decimal('grand_total', 18, 4)->default(0);
            $table->decimal('paid_amount', 18, 4)->default(0);
            $table->decimal('due_amount', 18, 4)->default(0);

            // Optional reference; verify Accounting V4 linkage before adding an FK.
            $table->unsignedBigInteger('journal_entry_id')->nullable();

            $table->text('notes')->nullable();
            $table->string('status', 30)->default('draft');

            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->unsignedBigInteger('confirmed_by')->nullable();
            $table->timestamp('confirmed_at')->nullable();
            $table->unsignedBigInteger('cancelled_by')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->text('cancellation_reason')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->unique(
                ['tenant_id', 'purchase_no'],
                'purchases_tenant_number_unique'
            );

            $table->index(
                ['tenant_id', 'purchase_date'],
                'purchases_tenant_date_index'
            );

            $table->index(
                ['tenant_id', 'supplier_id'],
                'purchases_tenant_supplier_index'
            );

            $table->index(
                ['tenant_id', 'status'],
                'purchases_tenant_status_index'
            );

            $table->index(
                ['tenant_id', 'purchase_order_id'],
                'purchases_tenant_po_index'
            );

            $table->index(
                ['tenant_id', 'goods_receipt_note_id'],
                'purchases_tenant_grn_index'
            );

            $table->index(
                ['tenant_id', 'due_date'],
                'purchases_tenant_due_date_index'
            );

            $table->index(
                ['tenant_id', 'supplier_invoice_number'],
                'purchases_tenant_supplier_invoice_index'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('purchases');
    }
};
