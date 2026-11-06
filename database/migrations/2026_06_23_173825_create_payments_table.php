<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (
            Blueprint $table
        ) {
            $table->id();

            $table->uuid('uuid')->unique();

            $table->foreignId('tenant_id')
                ->constrained('tenants')
                ->cascadeOnDelete();

            $table->string('payment_no', 50);

            $table->string('payment_type', 20);

            $table->date('payment_date');

            $table->string('party_type', 20);

            $table->unsignedBigInteger('party_id')->nullable();

            $table->string('purpose', 50);

            $table->unsignedBigInteger('account_id');

            $table->unsignedBigInteger('offset_account_id')->nullable();

            $table->decimal('amount', 18, 4);

            $table->string('payment_method', 30);

            $table->string('reference_no', 100)->nullable();

            $table->text('notes')->nullable();

            $table->string('status', 20);

            $table->timestamps();

            $table->softDeletes();

            $table->unique(
                ['tenant_id', 'payment_no'],
                'payments_tenant_payment_no_unique'
            );

            $table->index(
                ['tenant_id', 'payment_type'],
                'payments_tenant_type_index'
            );

            $table->index(
                ['tenant_id', 'payment_date'],
                'payments_tenant_date_index'
            );

            $table->index(
                ['tenant_id', 'party_type', 'party_id'],
                'payments_tenant_party_index'
            );

            $table->index(
                ['tenant_id', 'status'],
                'payments_tenant_status_index'
            );

            $table->index(
                ['tenant_id', 'account_id'],
                'payments_tenant_account_index'
            );

            $table->index(
                ['tenant_id', 'purpose'],
                'payments_tenant_purpose_index'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};