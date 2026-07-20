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

        Schema::create('journal_entries', function (Blueprint $table) {

            $table->id();

            $table->foreignId('tenant_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->string('voucher_no');

            $table->string('voucher_type', 100);

            $table->nullableMorphs('reference');

            $table->date('entry_date');

            $table->text('description')
                ->nullable();

            $table->enum('status', [
                'draft',
                'posted',
                'cancelled',
            ])->default('draft');

            $table->foreignId('created_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamps();

            $table->unique([
                'tenant_id',
                'voucher_no'
            ]);

            $table->index([
                'tenant_id',
                'entry_date'
            ]);

            $table->index([
                'tenant_id',
                'voucher_type'
            ]);

            $table->index([
                'tenant_id',
                'status'
            ]);
        });

        Schema::create('journal_entry_lines', function (Blueprint $table) {

            $table->id();

            $table->foreignId('tenant_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->foreignId('journal_entry_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->foreignId('chart_of_account_id')
                ->constrained('chart_of_accounts')
                ->cascadeOnDelete();

            $table->decimal(
                'debit',
                18,
                4
            )->default(0);

            $table->decimal(
                'credit',
                18,
                4
            )->default(0);

            $table->text('description')
                ->nullable();

            $table->timestamps();

            $table->index([
                'chart_of_account_id',
                'journal_entry_id'
            ]);
        });

        Schema::create(
            'account_ledgers',
            function (Blueprint $table) {

                $table->id();

                $table->foreignId('tenant_id')
                    ->constrained()
                    ->cascadeOnDelete();

                $table->foreignId('chart_of_account_id')
                    ->constrained('chart_of_accounts')
                    ->cascadeOnDelete();

                $table->foreignId('journal_entry_id')
                    ->constrained()
                    ->cascadeOnDelete();

                $table->foreignId('journal_entry_line_id')
                    ->constrained()
                    ->cascadeOnDelete();

                $table->date(
                    'entry_date'
                );

                $table->string(
                    'voucher_no'
                );

                $table->string(
                    'voucher_type'
                );

                $table->decimal(
                    'debit',
                    18,
                    4
                )->default(0);

                $table->decimal(
                    'credit',
                    18,
                    4
                )->default(0);

                $table->decimal(
                    'running_balance',
                    18,
                    4
                )->default(0);

                $table->text(
                    'description'
                )->nullable();

                $table->timestamps();

                $table->index([
                    'tenant_id',
                    'chart_of_account_id',
                    'entry_date'
                ]);
            }
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('account_ledgers');
        Schema::dropIfExists('journal_entry_lines');
        Schema::dropIfExists('journal_entries');
        Schema::dropIfExists('chart_of_accounts');
        Schema::dropIfExists('account_groups');
    }
};