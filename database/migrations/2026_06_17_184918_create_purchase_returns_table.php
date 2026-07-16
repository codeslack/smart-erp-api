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
        Schema::create('purchase_returns', function (Blueprint $table) {

            $table->id();

            $table->foreignId('tenant_id')
                ->constrained('tenants')
                ->cascadeOnDelete();

            $table->foreignId('purchase_id')
                ->constrained('purchases')
                ->cascadeOnDelete();

            $table->foreignId('supplier_id')
                ->constrained('suppliers')
                ->cascadeOnDelete();

            $table->string('return_no');

            $table->date('return_date');

            $table->decimal(
                'subtotal',
                18,
                4
            )->default(0);

            $table->decimal(
                'discount',
                18,
                4
            )->default(0);

            $table->decimal(
                'tax',
                18,
                4
            )->default(0);

            $table->decimal(
                'grand_total',
                18,
                4
            )->default(0);

            $table->decimal(
                'refund_amount',
                18,
                4
            )->default(0);

            $table->decimal(
                'credited_amount',
                18,
                4
            )->default(0);

            $table->string('refund_type')
                ->default('credit_note');

            $table->string('return_reason')
                ->nullable();

            $table->string('status')
                ->default('draft');

            $table->foreignId('approved_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamp('approved_at')
                ->nullable();

            $table->text('notes')
                ->nullable();

            $table->timestamps();

            $table->unique([
                'tenant_id',
                'return_no'
            ]);

            $table->index('tenant_id');

            $table->index([
                'tenant_id',
                'supplier_id',
            ]);

            $table->index([
                'tenant_id',
                'status'
            ]);

            $table->index([
                'tenant_id',
                'return_date'
            ]);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('purchase_returns');
    }
};
