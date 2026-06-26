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
        Schema::create('journal_entries', function (Blueprint $table) {

            $table->id();

            $table->foreignId('tenant_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->string('voucher_no');

            $table->enum('voucher_type', [
                'sale',
                'purchase',
                'customer_receipt',
                'supplier_payment',
                'sales_return',
                'purchase_return',
                'journal',
            ]);

            $table->nullableMorphs('reference');

            $table->date('entry_date');

            $table->text('description')
                ->nullable();

            $table->enum('status', [
                'draft',
                'posted',
                'cancelled',
            ])->default('draft');

            $table->foreignId('created_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamps();

            $table->unique([
                'tenant_id',
                'voucher_no'
            ]);

            $table->index([
                'tenant_id',
                'entry_date'
            ]);

            $table->index([
                'tenant_id',
                'voucher_type'
            ]);

            $table->index([
                'tenant_id',
                'status'
            ]);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('journal_entries');
    }
};
