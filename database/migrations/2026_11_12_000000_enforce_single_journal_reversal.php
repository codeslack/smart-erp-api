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

            // A posted journal may have at most one reversal per tenant.
            // Multiple NULL values remain valid for original journal entries.
            $table->unique(
                ['tenant_id', 'reversal_of_journal_entry_id'],
                'je_tenant_reversal_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::table('journal_entries', function (Blueprint $table) {
            $table->dropUnique('je_tenant_reversal_unique');

            $table->index(
                ['tenant_id', 'reversal_of_journal_entry_id'],
                'je_tenant_reversal_idx'
            );
        });
    }
};
