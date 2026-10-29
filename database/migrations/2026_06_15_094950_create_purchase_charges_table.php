<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('purchase_charges', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();

            $table->foreignId('tenant_id')
                ->constrained('tenants')->cascadeOnDelete();

            $table->foreignId('purchase_id')
                ->constrained('purchases')->cascadeOnDelete();

            $table->string('charge_type', 50);
            $table->string('name', 150);

            // fixed or percentage.
            $table->string('calculation_method', 20)->default('fixed');
            $table->decimal('calculation_value', 18, 4)->default(0);

            // Amount before tax and tax amount.
            $table->decimal('amount', 18, 4)->default(0);

            $table->unsignedBigInteger('tax_id')->nullable();
            $table->decimal('tax_rate', 8, 4)->default(0);
            $table->decimal('tax_amount', 18, 4)->default(0);

            // expense = expensed separately; landed_cost = allocated into inventory cost.
            $table->string('treatment', 20)->default('expense');

            // by_value, by_quantity, or equal.
            $table->string('allocation_method', 20)->default('by_value');

            $table->boolean('affects_inventory_cost')->default(false);

            // Link to Accounting V4 chart_of_accounts.
            $table->foreignId('chart_of_account_id')
                ->nullable()->constrained('chart_of_accounts')->nullOnDelete();

            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
            $table->softDeletes();

            $table->index(
                ['tenant_id', 'purchase_id'],
                'purchase_charges_tenant_purchase_index'
            );

            $table->index(
                ['tenant_id', 'charge_type'],
                'purchase_charges_tenant_type_index'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_charges');
    }
};
