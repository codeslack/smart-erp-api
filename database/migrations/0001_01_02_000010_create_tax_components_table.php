<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tax_components', function (Blueprint $table) {
            $table->id();

            $table->uuid('uuid')->unique();

            $table->foreignId('tenant_id')
                ->constrained('tenants')
                ->cascadeOnDelete();

            $table->string('code', 50);
            $table->string('name', 100);

            $table->string('component_type', 50);

            $table->boolean('is_active')->default(true);

            $table->timestamps();
            $table->softDeletes();

            $table->unique(
                ['tenant_id', 'code'],
                'tax_components_tenant_code_unique'
            );

            $table->index(
                ['tenant_id', 'component_type'],
                'tax_components_tenant_type_index'
            );

            $table->index(
                ['tenant_id', 'is_active'],
                'tax_components_tenant_active_index'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tax_components');
    }
};