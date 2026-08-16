<?php

use App\Modules\Tenant\Enums\BusinessTypeEnum;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenants', function (
            Blueprint $table
        ) {

            $table->id();

            $table->uuid('uuid')
                ->unique();

            $table->string('code')
                ->unique();

            $table->string('name');

            $table->string('slug')
                ->unique();

            $table->string('domain')
                ->nullable();

            $table->string(
                'business_type',
                50
            )->default(
                BusinessTypeEnum::GENERAL->value
            );

            $table->boolean('is_active')
                ->default(true);

            $table->timestamps();

            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenants');
    }
};
