<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sales', function (
            Blueprint $table
        ) {
            $table->id();

            $table->foreignId('tenant_id')
                ->constrained('tenants')
                ->cascadeOnDelete();

            $table->string('sale_no')
                ->unique();

            $table->foreignId('customer_id')
                ->constrained('customers')
                ->cascadeOnDelete();

            $table->date('sale_date');

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
                'paid_amount',
                18,
                4
            )->default(0);

            $table->decimal(
                'due_amount',
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
                'sale_date'
            ]);

            $table->index([
                'tenant_id',
                'customer_id',
            ]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(
            'sales'
        );
    }
};
