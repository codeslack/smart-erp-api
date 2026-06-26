<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('account_groups', function (
            Blueprint $table
        ) {

            $table->id();

            $table->foreignId('tenant_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->string('name');

            $table->string('code')
                ->nullable();

            $table->timestamps();

            $table->unique([
                'tenant_id',
                'name'
            ]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(
            'account_groups'
        );
    }
};