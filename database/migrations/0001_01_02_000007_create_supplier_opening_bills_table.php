<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('supplier_opening_bills', function (
            Blueprint $table
        ) {
            
            $table->id();
            $table->uuid('uuid')->unique();

            $table->foreignId('tenant_id')
                ->constrained('tenants')
                ->cascadeOnDelete();

            $table->foreignId('supplier_id')
                ->constrained('suppliers')
                ->cascadeOnDelete();

            $table->string('bill_no', 100);
            $table->date('bill_date');
            $table->date('due_date')->nullable();

            $table->string('opening_balance_type', 20);

            $table->decimal('amount', 18, 4);
            $table->decimal('balance_amount', 18, 4);

            $table->text('remarks')->nullable();

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

            $table->unique(
                ['tenant_id', 'bill_no'],
                'supplier_opening_bills_tenant_bill_no_unique'
            );

            $table->index(
                ['tenant_id', 'supplier_id'],
                'supplier_opening_bills_tenant_supplier_index'
            );

            $table->index(
                ['tenant_id', 'bill_date'],
                'supplier_opening_bills_tenant_bill_date_index'
            );

            $table->index(
                ['tenant_id', 'due_date'],
                'supplier_opening_bills_tenant_due_date_index'
            );

            $table->index(
                ['tenant_id', 'opening_balance_type'],
                'supplier_opening_bills_tenant_balance_type_index'
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('supplier_opening_bills');
    }
};
