<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('taxes', function (Blueprint $table) {
            $table->id();

            $table->uuid('uuid')->unique();

            $table->foreignId('tenant_id')
                ->constrained('tenants')
                ->cascadeOnDelete();

            $table->string('code', 50);
            $table->string('name', 150);

            $table->string('tax_type', 50);

            $table->text('description')->nullable();

            $table->boolean('is_active')->default(true);

            $table->date('effective_from');
            $table->date('effective_to')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->unique(
                ['tenant_id', 'code'],
                'taxes_tenant_code_unique'
            );

            $table->index(
                ['tenant_id', 'is_active'],
                'taxes_tenant_active_index'
            );

            $table->index(
                ['tenant_id', 'effective_from', 'effective_to'],
                'taxes_tenant_effective_index'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('taxes');
    }
};