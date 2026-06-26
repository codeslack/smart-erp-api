<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
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
        Schema::dropIfExists(
            'account_ledgers'
        );
    }
};
