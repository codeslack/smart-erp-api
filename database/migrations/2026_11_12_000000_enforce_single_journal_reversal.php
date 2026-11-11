<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('journal_entries', function (Blueprint $table) {
            $table->dropIndex('je_tenant_reversal_idx');

            // Journal entry IDs are globally unique, so one original can
            // have only one reversal even if a tenant-context bug occurs.
            // MySQL permits multiple NULL values for original entries.
            $table->unique(
                'reversal_of_journal_entry_id',
                'je_reversal_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::table('journal_entries', function (Blueprint $table) {
            $table->dropUnique('je_reversal_unique');

            $table->index(
                ['tenant_id', 'reversal_of_journal_entry_id'],
                'je_tenant_reversal_idx'
            );
        });
    }
};
