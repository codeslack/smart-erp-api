<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('goods_receipt_note_items', function (Blueprint $table) {

            $table->id();

            $table->foreignId(
                'goods_receipt_note_id'
            )->constrained()->cascadeOnDelete();

            $table->foreignId(
                'product_id'
            )->constrained()->cascadeOnDelete();

            $table->foreignId(
                'warehouse_id'
            )->constrained()->cascadeOnDelete();

            $table->decimal(
                'ordered_quantity',
                15,
                4
            );

            $table->decimal(
                'received_quantity',
                15,
                4
            );

            $table->decimal(
                'pending_quantity',
                15,
                4
            );

            $table->decimal(
                'unit_cost',
                15,
                4
            );

            $table->decimal(
                'line_total',
                15,
                4
            );

            $table->timestamps();

            $table->index([
                'product_id',
                'warehouse_id',
            ]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(
            'goods_receipt_note_items'
        );
    }
};
