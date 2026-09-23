<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customers', function (
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
            | Identity
            |--------------------------------------------------------------------------
            */

            $table->string('code', 50)
                ->nullable();

            $table->string('name');

            $table->string('contact_person')
                ->nullable();

            /*
            |--------------------------------------------------------------------------
            | Contact Information
            |--------------------------------------------------------------------------
            */

            $table->string('phone')
                ->nullable();

            $table->string('email')
                ->nullable();

            $table->text('address')
                ->nullable();

            /*
            |--------------------------------------------------------------------------
            | Tax Information
            |--------------------------------------------------------------------------
            */

            $table->string('tax_number')
                ->nullable();

            /*
            |--------------------------------------------------------------------------
            | Credit Control
            |--------------------------------------------------------------------------
            */

            $table->foreignId('payment_term_id')
                ->nullable()
                ->constrained('payment_terms')
                ->nullOnDelete();

            $table->unsignedInteger('credit_days')
                ->nullable();

            $table->decimal(
                'credit_limit',
                18,
                4
            )->default(0);

            $table->string(
                'credit_control',
                20
            )->default('none');

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
                ['tenant_id', 'code'],
                'customers_tenant_code_unique'
            );

            $table->unique(
                ['tenant_id', 'name'],
                'customers_tenant_name_unique'
            );

            /*
            |--------------------------------------------------------------------------
            | Indexes
            |--------------------------------------------------------------------------
            */

            $table->index('name');

            $table->index('is_active');

            $table->index('credit_control');

            $table->index([
                'tenant_id',
                'is_active',
            ]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(
            'customers'
        );
    }
};