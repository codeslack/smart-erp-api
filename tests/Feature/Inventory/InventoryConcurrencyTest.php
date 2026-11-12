<?php

namespace Tests\Feature\Inventory;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Database\QueryException;
use Tests\Support\CreatesTenant;
use App\Core\Tenant\TenantManager;
use App\Modules\Tenant\Models\Tenant;
use App\Modules\Product\Models\Product;
use App\Modules\Warehouse\Models\Warehouse;
use App\Modules\Product\Enums\ProductTypeEnum;
use App\Modules\Product\Enums\InventoryTrackingTypeEnum;
use App\Modules\SystemNumber\Enums\SystemNumberTypeEnum;
use App\Modules\Inventory\Models\ProductStock;
use App\Modules\Inventory\Models\StockLedger;
use App\Modules\Inventory\Repositories\Contracts\ProductStockRepositoryInterface;
use App\Modules\Inventory\Data\InventoryMovementData;
use App\Modules\Inventory\Services\InventoryPostingService;
use App\Modules\Inventory\Enums\InventoryTransactionTypeEnum;

class InventoryConcurrencyTest extends TestCase
{
    use RefreshDatabase;
    use CreatesTenant;

    protected Tenant $tenant;
    protected Product $product;
    protected Warehouse $warehouse;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = $this->createTestTenant();

        app(TenantManager::class)->setTenant($this->tenant);

        $this->product = Product::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Concurrency Product',
            'code' => nextSystemNumber(SystemNumberTypeEnum::PRODUCT),
            'sku' => 'CON-001',
            'slug' => 'con-001-' . random_int(1000, 9999),
            'product_type' => ProductTypeEnum::PRODUCT,
            'inventory_tracking_type' => InventoryTrackingTypeEnum::NONE,
            'purchase_price' => 10,
            'selling_price' => 20,
            'is_active' => true,
        ]);

        $this->warehouse = Warehouse::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Concurrency Warehouse',
            'code' => nextSystemNumber(SystemNumberTypeEnum::WAREHOUSE),
            'is_active' => true,
        ]);
    }

    public function test_multiple_stock_out_operations_never_exceed_available_stock(): void
    {
        $service = app(InventoryPostingService::class);

        $service->stockIn(new InventoryMovementData(
            productId: $this->product->id,
            productVariantId: null,
            warehouseId: $this->warehouse->id,
            quantity: 100,
            unitCost: 10,
            transactionType: InventoryTransactionTypeEnum::OPENING_STOCK->value,
            transactionDate: now(),
            referenceType: null,
            referenceId: null,
            referenceNo: 'CON-OPEN-001',
            batchId: null,
            serialId: null,
            remarks: 'Concurrency test opening stock',
        ));

        $successful = 0;
        $failed = 0;

        foreach (range(1, 10) as $index) {
            try {
                $service->stockOut(new InventoryMovementData(
                    productId: $this->product->id,
                    productVariantId: null,
                    warehouseId: $this->warehouse->id,
                    quantity: 15,
                    unitCost: 10,
                    transactionType: InventoryTransactionTypeEnum::SALE->value,
                    transactionDate: now(),
                    referenceType: null,
                    referenceId: null,
                    referenceNo: 'CON-OUT-' . str_pad(
                        (string) $index,
                        3,
                        '0',
                        STR_PAD_LEFT
                    ),
                    batchId: null,
                    serialId: null,
                    remarks: 'Concurrency stock-out test',
                ));

                $successful++;
            } catch (\Throwable) {
                $failed++;
            }
        }

        $stock = ProductStock::query()
            ->where('product_id', $this->product->id)
            ->where('warehouse_id', $this->warehouse->id)
            ->firstOrFail();

        $ledgerCount = StockLedger::query()
            ->where('product_id', $this->product->id)
            ->where('warehouse_id', $this->warehouse->id)
            ->where('transaction_type', InventoryTransactionTypeEnum::SALE)
            ->count();

        $this->assertSame(6, $successful);

        $this->assertSame(4, $failed);

        $this->assertEquals(10, (float) $stock->quantity);

        $this->assertSame(6, $ledgerCount);

        $this->assertGreaterThanOrEqual(
            0,
            (float) $stock->quantity
        );
    }

    public function test_failed_stock_out_does_not_create_partial_ledger(): void
    {
        $service = app(InventoryPostingService::class);

        $service->stockIn(new InventoryMovementData(
            productId: $this->product->id,
            productVariantId: null,
            warehouseId: $this->warehouse->id,
            quantity: 20,
            unitCost: 10,
            transactionType: InventoryTransactionTypeEnum::OPENING_STOCK->value,
            transactionDate: now(),
            referenceType: null,
            referenceId: null,
            referenceNo: 'CON-ROLLBACK-OPEN',
            batchId: null,
            serialId: null,
            remarks: 'Rollback test opening stock',
        ));

        try {
            $service->stockOut(new InventoryMovementData(
                productId: $this->product->id,
                productVariantId: null,
                warehouseId: $this->warehouse->id,
                quantity: 50,
                unitCost: 10,
                transactionType: InventoryTransactionTypeEnum::SALE->value,
                transactionDate: now(),
                referenceType: null,
                referenceId: null,
                referenceNo: 'CON-ROLLBACK-OUT',
                batchId: null,
                serialId: null,
                remarks: 'Should fail and rollback',
            ));
        } catch (\Throwable) {
            // Expected failure.
        }

        $stock = ProductStock::query()
            ->where('product_id', $this->product->id)
            ->where('warehouse_id', $this->warehouse->id)
            ->firstOrFail();

        $this->assertEquals(20, (float) $stock->quantity);

        $this->assertDatabaseMissing('stock_ledgers', [
            'reference_no' => 'CON-ROLLBACK-OUT',
        ]);
    }

    public function test_non_variant_stock_row_is_reused_by_get_or_create(): void
    {
        $repository = app(ProductStockRepositoryInterface::class);

        $first = $repository->getOrCreateForUpdate(
            $this->product->id,
            null,
            $this->warehouse->id,
        );

        $second = $repository->getOrCreateForUpdate(
            $this->product->id,
            null,
            $this->warehouse->id,
        );

        $this->assertSame($first->id, $second->id);
        $this->assertDatabaseCount('product_stocks', 1);
    }

    public function test_database_rejects_duplicate_non_variant_stock_rows(): void
    {
        ProductStock::create([
            'tenant_id' => $this->tenant->id,
            'product_id' => $this->product->id,
            'product_variant_id' => null,
            'warehouse_id' => $this->warehouse->id,
            'quantity' => 5,
            'average_cost' => 10,
        ]);

        try {
            ProductStock::create([
                'tenant_id' => $this->tenant->id,
                'product_id' => $this->product->id,
                'product_variant_id' => null,
                'warehouse_id' => $this->warehouse->id,
                'quantity' => 7,
                'average_cost' => 12,
            ]);

            $this->fail('A duplicate non-variant stock row should violate the unique constraint.');
        } catch (QueryException) {
            $this->assertDatabaseCount('product_stocks', 1);
        }
    }

    public function test_get_or_create_restores_a_soft_deleted_stock_row(): void
    {
        $stock = ProductStock::create([
            'tenant_id' => $this->tenant->id,
            'product_id' => $this->product->id,
            'product_variant_id' => null,
            'warehouse_id' => $this->warehouse->id,
            'quantity' => 12,
            'average_cost' => 25,
        ]);

        $stock->delete();

        $restored = app(ProductStockRepositoryInterface::class)
            ->getOrCreateForUpdate(
                $this->product->id,
                null,
                $this->warehouse->id,
            );

        $this->assertSame($stock->id, $restored->id);
        $this->assertFalse($restored->trashed());
        $this->assertEquals(12, (float) $restored->quantity);
        $this->assertEquals(25, (float) $restored->average_cost);
    }

}
