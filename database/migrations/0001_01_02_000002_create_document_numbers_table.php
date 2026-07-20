<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('document_numbers', function (
            Blueprint $table
        ) {

            $table->id();

            $table->foreignId('tenant_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->string(
                'document_type',
                100
            );

            $table->string(
                'financial_year',
                20
            );

            $table->unsignedBigInteger(
                'current_number'
            )->default(0);

            $table->timestamps();

            $table->unique([
                'tenant_id',
                'document_type',
                'financial_year',
            ]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(
            'document_numbers'
        );
    }
};