<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('delivery_note_items', function (Blueprint $table) {

            $table->id();

            $table->foreignId(
                'tenant_id'
            )->constrained()->cascadeOnDelete();

            $table->foreignId(
                'delivery_note_id'
            )->constrained()->cascadeOnDelete();

            $table->foreignId(
                'product_id'
            )->constrained()->cascadeOnDelete();

            $table->foreignId(
                'warehouse_id'
            )->constrained()->cascadeOnDelete();

            $table->decimal(
                'ordered_quantity',
                18,
                4
            );

            $table->decimal(
                'delivered_quantity',
                18,
                4
            );

            $table->decimal(
                'pending_quantity',
                18,
                4
            );

            $table->decimal(
                'unit_price',
                18,
                4
            );

            $table->decimal(
                'line_total',
                18,
                4
            );

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(
            'delivery_note_items'
        );
    }
};
