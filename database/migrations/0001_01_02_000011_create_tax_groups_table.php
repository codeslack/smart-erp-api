<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tax_groups', function (Blueprint $table) {
            $table->id();

            $table->uuid('uuid')->unique();

            $table->foreignId('tenant_id')
                ->constrained('tenants')
                ->cascadeOnDelete();

            $table->foreignId('tax_id')
                ->constrained('taxes')
                ->cascadeOnDelete();

            $table->string('code', 50);
            $table->string('name', 150);

            $table->string('usage', 50);
            $table->string('direction', 50);
            $table->string('treatment', 50);

            $table->boolean('is_active')->default(true);

            $table->date('effective_from');
            $table->date('effective_to')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->unique(
                ['tenant_id', 'code'],
                'tax_groups_tenant_code_unique'
            );

            $table->index(
                ['tenant_id', 'tax_id'],
                'tax_groups_tenant_tax_index'
            );

            $table->index(
                ['tenant_id', 'usage', 'direction'],
                'tax_groups_tenant_usage_direction_index'
            );

            $table->index(
                ['tenant_id', 'is_active'],
                'tax_groups_tenant_active_index'
            );

            $table->index(
                ['tenant_id', 'effective_from', 'effective_to'],
                'tax_groups_effective_index'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tax_groups');
    }
};