<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('receipt_allocations', function (
            Blueprint $table
        ) {

            $table->id();

            $table->uuid('uuid')
                ->unique();

            $table->foreignId('tenant_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->foreignId('receipt_id')
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
            'receipt_allocations'
        );
    }
};
