<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('chart_of_accounts', function (
            Blueprint $table
        ) {

            $table->id();

            $table->foreignId('tenant_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->foreignId('account_group_id')
                ->constrained('account_groups')
                ->cascadeOnDelete();

            $table->foreignId('parent_id')
                ->nullable()
                ->constrained('chart_of_accounts')
                ->nullOnDelete();

            $table->string(
                'account_code'
            );

            $table->string(
                'account_name'
            );

            $table->enum(
                'account_type',
                [
                    'asset',
                    'liability',
                    'equity',
                    'income',
                    'expense',
                ]
            );

            $table->decimal(
                'opening_balance',
                18,
                4
            )->default(0);

            $table->decimal(
                'current_balance',
                18,
                4
            )->default(0);

            $table->boolean(
                'is_system'
            )->default(false);

            $table->boolean(
                'is_active'
            )->default(true);

            $table->timestamps();

            $table->unique([
                'tenant_id',
                'account_code'
            ]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(
            'chart_of_accounts'
        );
    }
};
