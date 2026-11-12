<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // MySQL unique indexes allow multiple NULL values. Refuse to apply
        // the new identity constraint if historical duplicate balances exist;
        // merging inventory quantities/valuation automatically would be unsafe.
        $duplicate = DB::table('product_stocks')
            ->select('tenant_id', 'product_id', 'warehouse_id')
            ->selectRaw('COALESCE(product_variant_id, 0) AS product_variant_key')
            ->groupBy('tenant_id', 'product_id', 'warehouse_id')
            ->groupByRaw('COALESCE(product_variant_id, 0)')
            ->havingRaw('COUNT(*) > 1')
            ->first();

        if ($duplicate !== null) {
            throw new RuntimeException(
                'Duplicate product stock identities exist. Reconcile duplicate '
                . 'product/variant/warehouse balances before applying this migration.'
            );
        }

        Schema::table('product_stocks', function (Blueprint $table) {
            $table->unsignedBigInteger('product_variant_key')
                ->default(0);
        });

        DB::table('product_stocks')->update([
            'product_variant_key' => DB::raw('COALESCE(product_variant_id, 0)'),
        ]);

        Schema::table('product_stocks', function (Blueprint $table) {
            $table->dropUnique('product_stocks_unique');

            $table->unique(
                [
                    'tenant_id',
                    'product_id',
                    'product_variant_key',
                    'warehouse_id',
                ],
                'product_stocks_identity_unique'
            );

            // A stock balance must retain its variant identity. Soft-delete
            // variants instead of physically nulling a referenced stock row.
            $table->dropForeign(['product_variant_id']);
            $table->foreign('product_variant_id')
                ->references('id')
                ->on('product_variants')
                ->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('product_stocks', function (Blueprint $table) {
            $table->dropUnique('product_stocks_identity_unique');
            $table->dropForeign(['product_variant_id']);
            $table->foreign('product_variant_id')
                ->references('id')
                ->on('product_variants')
                ->nullOnDelete();
        });

        Schema::table('product_stocks', function (Blueprint $table) {
            $table->dropColumn('product_variant_key');
        });

        Schema::table('product_stocks', function (Blueprint $table) {
            $table->unique(
                [
                    'tenant_id',
                    'product_id',
                    'product_variant_id',
                    'warehouse_id',
                ],
                'product_stocks_unique'
            );
        });
    }
};
