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
        Schema::create('sales_quotations', function (Blueprint $table) {

            $table->id();

            $table->foreignId('tenant_id')
                ->constrained('tenants')
                ->cascadeOnDelete();

            $table->foreignId('customer_id')
                ->constrained('customers')
                ->cascadeOnDelete();

            $table->string('quotation_no')
                ->unique();

            $table->date('quotation_date');

            $table->decimal(
                'subtotal',
                18,
                4
            )->default(0);

            $table->decimal(
                'discount_amount',
                18,
                4
            )->default(0);

            $table->decimal(
                'tax_amount',
                18,
                4
            )->default(0);

            $table->decimal(
                'grand_total',
                18,
                4
            )->default(0);

            $table->string('status')
                ->default('draft');

            $table->text('notes')
                ->nullable();

            $table->timestamps();

            $table->index([
                'tenant_id',
                'customer_id'
            ]);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sales_quotations');
    }
};
