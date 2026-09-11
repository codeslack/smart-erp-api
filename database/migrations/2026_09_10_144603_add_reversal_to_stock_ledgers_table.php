<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stock_ledgers', function (
            Blueprint $table
        ) {
            $table->foreignId(
                'reversal_of_ledger_id'
            )
                ->nullable()
                ->after('reference_no')
                ->constrained('stock_ledgers')
                ->restrictOnDelete();

            $table->unique(
                [
                    'tenant_id',
                    'reversal_of_ledger_id',
                ],
                'sl_tenant_reversal_unique'
            );

            $table->index(
                [
                    'tenant_id',
                    'reversal_of_ledger_id',
                ],
                'sl_tenant_reversal_idx'
            );
        });
    }

    public function down(): void
    {
        Schema::table('stock_ledgers', function (
            Blueprint $table
        ) {
            $table->dropUnique(
                'sl_tenant_reversal_unique'
            );

            $table->dropIndex(
                'sl_tenant_reversal_idx'
            );

            $table->dropForeign(
                ['reversal_of_ledger_id']
            );

            $table->dropColumn(
                'reversal_of_ledger_id'
            );
        });
    }
};