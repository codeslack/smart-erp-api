<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('advance_allocations', function (Blueprint $table) {

            $table->id();

            $table->foreignId('tenant_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->enum(
                'allocation_type',
                [
                    'customer',
                    'supplier',
                ]
            );

            $table->string('source_type');

            $table->unsignedBigInteger(
                'source_id'
            );

            $table->string('target_type');

            $table->unsignedBigInteger(
                'target_id'
            );

            $table->decimal(
                'allocated_amount',
                18,
                4
            );

            $table->timestamp(
                'allocated_at'
            );

            $table->foreignId(
                'created_by'
            )
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamps();

            $table->index([
                'tenant_id',
                'allocation_type',
            ]);

            $table->index([
                'source_type',
                'source_id',
            ]);

            $table->index([
                'target_type',
                'target_id',
            ]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(
            'advance_allocations'
        );
    }
};