<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tax_accounting_mappings', function (Blueprint $table) {
            $table->id();

            $table->uuid('uuid')->unique();

            $table->foreignId('tenant_id')
                ->constrained('tenants')
                ->cascadeOnDelete();

            $table->foreignId('tax_component_id')
                ->constrained('tax_components')
                ->cascadeOnDelete();

            $table->foreignId('account_id')
                ->constrained('chart_of_accounts')
                ->cascadeOnDelete();

            $table->string('direction', 50);

            $table->boolean('is_active')
                ->default(true);

            $table->date('effective_from');

            $table->date('effective_to')
                ->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index(
                ['tenant_id', 'tax_component_id'],
                'tax_accounting_mappings_component_index'
            );

            $table->index(
                ['tenant_id', 'account_id'],
                'tax_accounting_mappings_account_index'
            );

            $table->index(
                ['tenant_id', 'direction', 'is_active'],
                'tax_accounting_mappings_direction_active_index'
            );

            $table->index(
                ['tenant_id', 'effective_from', 'effective_to'],
                'tax_accounting_mappings_effective_index'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tax_accounting_mappings');
    }
};