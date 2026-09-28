<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_allocations', function (
            Blueprint $table
        ) {
            
            $table->id();

            $table->uuid('uuid')->unique();

            $table->foreignId('tenant_id')
                ->constrained('tenants')
                ->cascadeOnDelete();

            $table->foreignId('payment_id')
                ->constrained('payments')
                ->cascadeOnDelete();

            $table->string('document_type', 50);

            $table->unsignedBigInteger('document_id');

            $table->decimal('allocated_amount', 18, 4);

            $table->timestamps();

            $table->index(
                ['tenant_id', 'payment_id'],
                'payment_allocations_tenant_payment_index'
            );

            $table->index(
                ['tenant_id', 'document_type', 'document_id'],
                'payment_allocations_tenant_document_index'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_allocations');
    }
};
