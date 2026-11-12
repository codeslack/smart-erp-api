<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $duplicate = DB::table('product_stocks')
            ->selectRaw(
                'tenant_id, product_id, COALESCE(product_variant_id, 0) AS variant_key, warehouse_id, COUNT(*) AS row_count'
            )
            ->groupBy('tenant_id', 'product_id', 'variant_key', 'warehouse_id')
            ->havingRaw('COUNT(*) > 1')
            ->first();

        if ($duplicate !== null) {
            throw new RuntimeException(
                'Cannot enforce product stock uniqueness: duplicate balance rows exist for tenant '
                . $duplicate->tenant_id
                . ', product '
                . $duplicate->product_id
                . ', warehouse '
                . $duplicate->warehouse_id
                . '. Reconcile duplicate stock balances before migrating.'
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
                ['tenant_id', 'product_id', 'product_variant_key', 'warehouse_id'],
                'ps_tenant_product_variant_wh_uq'
            );
        });
    }

    public function down(): void
    {
        Schema::table('product_stocks', function (Blueprint $table) {
            $table->dropUnique('ps_tenant_product_variant_wh_uq');
            $table->dropColumn('product_variant_key');

            $table->unique(
                ['tenant_id', 'product_id', 'product_variant_id', 'warehouse_id'],
                'product_stocks_unique'
            );
        });
    }
};
