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

            $table->foreignId('tenant_id')
                ->constrained('tenants')
                ->cascadeOnDelete();

            $table->foreignId('supplier_id')
                ->constrained('suppliers')
                ->cascadeOnDelete();

            $table->string('purchase_no')
                ->unique();

            $table->date('purchase_date');

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

            $table->text('notes')
                ->nullable();

            $table->string('status')
                ->default('draft');

            $table->timestamps();

            $table->index([
                'tenant_id',
                'purchase_date'
            ]);

            $table->index([
                'tenant_id',
                'supplier_id'
            ]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('purchases');
    }
};