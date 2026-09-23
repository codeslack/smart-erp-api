<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_terms', function (
            Blueprint $table
        ) {

            $table->id();

            $table->uuid('uuid')
                ->unique();

            /*
            |--------------------------------------------------------------------------
            | Tenant
            |--------------------------------------------------------------------------
            */

            $table->foreignId('tenant_id')
                ->constrained('tenants')
                ->cascadeOnDelete();

            /*
            |--------------------------------------------------------------------------
            | Payment Term
            |--------------------------------------------------------------------------
            */

            $table->string('code', 50);

            $table->string('name');

            $table->unsignedInteger('due_days')
                ->default(0);

            $table->text('description')
                ->nullable();

            /*
            |--------------------------------------------------------------------------
            | Status
            |--------------------------------------------------------------------------
            */

            $table->boolean('is_active')
                ->default(true);

            /*
            |--------------------------------------------------------------------------
            | Audit
            |--------------------------------------------------------------------------
            */

            $table->foreignId('created_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->foreignId('updated_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamps();

            $table->softDeletes();

            /*
            |--------------------------------------------------------------------------
            | Unique Constraints
            |--------------------------------------------------------------------------
            */

            $table->unique(
                [
                    'tenant_id',
                    'code',
                ],
                'payment_terms_tenant_code_unique'
            );

            $table->unique(
                [
                    'tenant_id',
                    'name',
                ],
                'payment_terms_tenant_name_unique'
            );

            /*
            |--------------------------------------------------------------------------
            | Indexes
            |--------------------------------------------------------------------------
            */

            $table->index(
                [
                    'tenant_id',
                    'is_active',
                ],
                'payment_terms_tenant_active_index'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(
            'payment_terms'
        );
    }
};