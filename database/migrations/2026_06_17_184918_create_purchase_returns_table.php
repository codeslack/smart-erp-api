<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('purchase_returns', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();

            $table->foreignId('tenant_id')
                ->constrained('tenants')
                ->cascadeOnDelete();

            $table->foreignId('purchase_id')
                ->constrained('purchases')
                ->restrictOnDelete();

            $table->foreignId('supplier_id')
                ->constrained('suppliers')
                ->restrictOnDelete();

            $table->string('return_no', 50);
            $table->date('return_date');

            $table->decimal('subtotal', 18, 4)->default(0);
            $table->decimal('discount_amount', 18, 4)->default(0);
            $table->decimal('tax_amount', 18, 4)->default(0);
            $table->decimal('rounding_amount', 18, 4)->default(0);
            $table->decimal('grand_total', 18, 4)->default(0);

            $table->decimal('refund_amount', 18, 4)->default(0);
            $table->decimal('credited_amount', 18, 4)->default(0);

            $table->string('refund_type', 30)->default('credit_note');

            $table->foreignId('refund_account_id')
                ->nullable()
                ->constrained('chart_of_accounts')
                ->nullOnDelete();

            $table->string('return_reason', 255)->nullable();
            $table->string('status', 30)->default('draft');

            $table->unsignedBigInteger('journal_entry_id')->nullable();

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
            $table->text('notes')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->unique(
                ['tenant_id', 'return_no'],
                'pr_tenant_return_no_unique'
            );

            $table->index(
                ['tenant_id', 'return_date'],
                'pr_tenant_date_index'
            );

            $table->index(
                ['tenant_id', 'supplier_id'],
                'pr_tenant_supplier_index'
            );

            $table->index(
                ['tenant_id', 'purchase_id'],
                'pr_tenant_purchase_index'
            );

            $table->index(
                ['tenant_id', 'status'],
                'pr_tenant_status_index'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_returns');
    }
};
