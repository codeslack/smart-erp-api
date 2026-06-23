<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customer_receipt_allocations', function (Blueprint $table) {

            $table->id();

            $table->foreignId('tenant_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->foreignId('customer_receipt_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->foreignId('sale_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->decimal(
                'allocated_amount',
                18,
                4
            );

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(
            'customer_receipt_allocations'
        );
    }
};