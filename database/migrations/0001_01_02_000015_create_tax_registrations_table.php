<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tax_registrations', function (Blueprint $table) {
            $table->id();

            $table->uuid('uuid')->unique();

            $table->foreignId('tenant_id')
                ->constrained('tenants')
                ->cascadeOnDelete();

            $table->string('registrable_type', 100);
            $table->unsignedBigInteger('registrable_id');

            $table->string('registration_type', 50);

            $table->string('registration_number', 100);

            $table->string('country_code', 10)
                ->nullable();

            $table->string('state_code', 20)
                ->nullable();

            $table->boolean('is_primary')
                ->default(false);

            $table->boolean('is_active')
                ->default(true);

            $table->date('effective_from');

            $table->date('effective_to')
                ->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->unique(
                [
                    'tenant_id',
                    'registration_type',
                    'registration_number',
                ],
                'tax_registrations_tenant_type_number_unique'
            );

            $table->index(
                [
                    'tenant_id',
                    'registrable_type',
                    'registrable_id',
                ],
                'tax_registrations_registrable_index'
            );

            $table->index(
                [
                    'tenant_id',
                    'registration_type',
                    'is_active',
                ],
                'tax_registrations_type_active_index'
            );

            $table->index(
                [
                    'tenant_id',
                    'country_code',
                    'state_code',
                ],
                'tax_registrations_location_index'
            );

            $table->index(
                [
                    'tenant_id',
                    'effective_from',
                    'effective_to',
                ],
                'tax_registrations_effective_index'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tax_registrations');
    }
};