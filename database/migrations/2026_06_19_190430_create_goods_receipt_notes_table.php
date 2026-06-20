<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('goods_receipt_notes', function (Blueprint $table) {

            $table->id();

            $table->unsignedBigInteger(
                'tenant_id'
            );

            $table->foreignId(
                'purchase_order_id'
            )->constrained()->cascadeOnDelete();

            $table->foreignId(
                'supplier_id'
            )->constrained()->cascadeOnDelete();

            $table->string(
                'grn_no'
            )->unique();

            $table->date(
                'received_date'
            );

            $table->decimal(
                'grand_total',
                15,
                4
            )->default(
                0
            );

            $table->string(
                'status'
            )->default(
                'draft'
            );

            $table->text(
                'notes'
            )->nullable();

            $table->timestamps();

            $table->index([
                'tenant_id',
                'purchase_order_id',
            ]);

            $table->index([
                'tenant_id',
                'supplier_id',
            ]);

            $table->index([
                'tenant_id',
                'status',
            ]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(
            'goods_receipt_notes'
        );
    }
};
