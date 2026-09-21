<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_ledgers', function (
            Blueprint $table
        ) {
            $table->id();
            $table->uuid('uuid')->unique();

            // Tenant
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();

            // Product & Location
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->foreignId('product_variant_id')->nullable()->constrained('product_variants')->nullOnDelete();
            $table->foreignId('warehouse_id')->constrained('warehouses')->cascadeOnDelete();

            // Batch & Serial tracking
            $table->foreignId('product_batch_id')->nullable()->constrained('product_batches')->nullOnDelete();
            $table->foreignId('product_serial_id')->nullable()->constrained('product_serials')->nullOnDelete();

            // Transaction Info
            $table->string('transaction_type', 50);
            $table->dateTime('transaction_date');
            $table->unsignedBigInteger('sequence_no')->default(0);

            // References & Reversals
            $table->nullableMorphs('referenceable');
            $table->string('reference_no', 100)->nullable();
            $table->foreignId('reversal_of_ledger_id')
                ->nullable()
                ->constrained('stock_ledgers')
                ->restrictOnDelete();

            // Quantities (Precision 18,4 is great for high-precision or fractional stock)
            $table->decimal('quantity_in', 18, 4)->default(0);
            $table->decimal('quantity_out', 18, 4)->default(0);

            // Valuation Cost
            $table->decimal('unit_cost', 18, 4)->default(0);
            $table->decimal('total_cost', 18, 4)->default(0);

            // Running Balance states
            $table->decimal('balance_quantity', 18, 4)->default(0);
            $table->decimal('balance_average_cost', 18, 4)->default(0);

            $table->text('remarks')->nullable();

            // Audit
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            /*
            |--------------------------------------------------------------------------
            | Optimized Indexes (Explicitly Named)
            |--------------------------------------------------------------------------
            */
            // Note: Removed 'sl_tenant_prod_wh_idx' because it is covered by the mega index below
            $table->index(['tenant_id', 'product_variant_id'], 'sl_tenant_variant_idx');
            $table->index(['tenant_id', 'transaction_type'], 'sl_tenant_type_idx');
            $table->index(['tenant_id', 'transaction_date'], 'sl_tenant_date_idx');
            $table->index(['tenant_id', 'reference_no'], 'sl_tenant_ref_idx');
            $table->index(['tenant_id', 'product_batch_id'], 'sl_tenant_batch_idx');
            $table->index(['tenant_id', 'product_serial_id'], 'sl_tenant_serial_idx');

            // Enforces that a ledger entry can only be reversed once per tenant
            // Note: Removed the duplicate non-unique index for this pair
            $table->unique(['tenant_id', 'reversal_of_ledger_id'], 'sl_tenant_reversal_unique');

            // Mega composite index for rapid FIFO/LIFO/Weighted Average calculations and ledger sorting
            $table->index(
                ['tenant_id', 'product_id', 'warehouse_id', 'transaction_date', 'sequence_no'],
                'sl_tenant_prod_wh_date_seq_idx'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_ledgers');
    }
};
