<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tax_applicability_rules', function (Blueprint $table) {
            $table->id();

            $table->uuid('uuid')->unique();

            $table->foreignId('tenant_id')
                ->constrained('tenants')
                ->cascadeOnDelete();

            $table->foreignId('tax_group_id')
                ->constrained('tax_groups')
                ->cascadeOnDelete();

            $table->string('source_country_code', 10)->nullable();
            $table->string('source_state_code', 20)->nullable();

            $table->string('destination_country_code', 10)->nullable();
            $table->string('destination_state_code', 20)->nullable();

            $table->string('place_of_supply_state_code', 20)
                ->nullable();

            $table->boolean('source_registration_required')
                ->nullable();

            $table->boolean('destination_registration_required')
                ->nullable();

            $table->unsignedSmallInteger('priority')
                ->default(1);

            $table->boolean('is_active')
                ->default(true);

            $table->date('effective_from');

            $table->date('effective_to')
                ->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index(
                ['tenant_id', 'tax_group_id'],
                'tax_applicability_rules_group_index'
            );

            $table->index(
                [
                    'tenant_id',
                    'source_country_code',
                    'source_state_code',
                ],
                'tax_applicability_rules_source_index'
            );

            $table->index(
                [
                    'tenant_id',
                    'destination_country_code',
                    'destination_state_code',
                ],
                'tax_applicability_rules_destination_index'
            );

            $table->index(
                [
                    'tenant_id',
                    'place_of_supply_state_code',
                ],
                'tax_applicability_rules_pos_index'
            );

            $table->index(
                ['tenant_id', 'is_active', 'priority'],
                'tax_applicability_rules_active_priority_index'
            );

            $table->index(
                ['tenant_id', 'effective_from', 'effective_to'],
                'tax_applicability_rules_effective_index'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tax_applicability_rules');
    }
};