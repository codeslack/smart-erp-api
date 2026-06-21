<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('delivery_notes', function (Blueprint $table) {

            $table->id();

            $table->foreignId(
                'tenant_id'
            )->constrained()->cascadeOnDelete();

            $table->foreignId(
                'sales_order_id'
            )->constrained()->cascadeOnDelete();

            $table->foreignId(
                'customer_id'
            )->constrained()->cascadeOnDelete();

            $table->string(
                'dn_no'
            )->unique();

            $table->date(
                'delivery_date'
            );

            $table->decimal(
                'grand_total',
                18,
                4
            )->default(0);

            $table->string(
                'status'
            )->default(
                'draft'
            );

            $table->text(
                'notes'
            )->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(
            'delivery_notes'
        );
    }
};
