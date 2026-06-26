<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
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
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('journal_entry_lines');
    }
};
