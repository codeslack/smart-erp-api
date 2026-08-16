<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('units', function (
            Blueprint $table
        ) {

            $table->id();

            $table->uuid('uuid')
                ->unique();

            $table->foreignId('tenant_id')
                ->constrained('tenants')
                ->cascadeOnDelete()
                ->index();

            $table->string('code', 50);

            $table->string('name', 255);

            $table->string('short_name', 50);

            $table->text('description')
                ->nullable();

            $table->boolean('is_active')
                ->default(true);

            $table->timestamps();

            $table->softDeletes();

            $table->unique([
                'tenant_id',
                'code'
            ]);

            $table->unique([
                'tenant_id',
                'name'
            ]);

            $table->unique([
                'tenant_id',
                'short_name',
            ]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('units');
    }
};
