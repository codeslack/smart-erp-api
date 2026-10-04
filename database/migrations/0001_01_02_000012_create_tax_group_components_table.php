<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tax_group_components', function (Blueprint $table) {
            $table->id();

            $table->uuid('uuid')->unique();

            $table->foreignId('tenant_id')
                ->constrained('tenants')
                ->cascadeOnDelete();

            $table->foreignId('tax_group_id')
                ->constrained('tax_groups')
                ->cascadeOnDelete();

            $table->foreignId('tax_component_id')
                ->constrained('tax_components')
                ->cascadeOnDelete();

            $table->decimal('rate', 12, 6);

            $table->string('calculation_method', 50)
                ->default('PERCENTAGE');

            $table->date('effective_from');

            $table->date('effective_to')
                ->nullable();

            $table->boolean('is_active')
                ->default(true);

            $table->unsignedSmallInteger('sort_order')
                ->default(1);

            $table->timestamps();

            $table->index(
                ['tenant_id', 'tax_group_id'],
                'tax_group_components_group_index'
            );

            $table->index(
                ['tenant_id', 'tax_component_id'],
                'tax_group_components_component_index'
            );

            $table->index(
                ['tenant_id', 'is_active'],
                'tax_group_components_active_index'
            );

            $table->index(
                ['tenant_id', 'effective_from', 'effective_to'],
                'tax_group_components_effective_index'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tax_group_components');
    }
};