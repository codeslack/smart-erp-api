<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('supplier_payments', function (Blueprint $table) {

            $table->id();

            $table->foreignId('tenant_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->string('payment_no')
                ->unique();

            $table->foreignId('supplier_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->date('payment_date');

            $table->string('payment_type', 20)
                ->default('invoice');

            $table->string('payment_method')
                ->nullable();

            $table->string('reference_no')
                ->nullable();

            $table->decimal(
                'amount',
                18,
                4
            );

            $table->foreignId('payment_account_id')
                ->constrained('chart_of_accounts');

            $table->text('notes')
                ->nullable();

            $table->string('status');

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('supplier_payments');
    }
};
