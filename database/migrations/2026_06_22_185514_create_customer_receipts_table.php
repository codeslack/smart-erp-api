<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customer_receipts', function (Blueprint $table) {

            $table->id();

            $table->foreignId('tenant_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->string('receipt_no')->unique();

            $table->foreignId('customer_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->date('receipt_date');

            $table->string('receipt_type', 20)
                ->default('invoice');

            $table->string('payment_method')->nullable();

            $table->string('reference_no')->nullable();

            $table->decimal(
                'amount',
                18,
                4
            );

            $table->foreignId('payment_account_id')
                ->constrained('chart_of_accounts');

            $table->text('notes')->nullable();

            $table->string('status');

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(
            'customer_receipts'
        );
    }
};
