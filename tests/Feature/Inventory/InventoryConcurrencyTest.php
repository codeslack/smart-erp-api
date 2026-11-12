<?php

namespace Tests\Feature\Inventory;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
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

    public function test_first_stock_in_movements_reuse_one_stock_row(): void
    {
        $service = app(InventoryPostingService::class);

        foreach ([10, 5] as $index => $quantity) {
            $service->stockIn(new InventoryMovementData(
                productId: $this->product->id,
                productVariantId: null,
                warehouseId: $this->warehouse->id,
                quantity: $quantity,
                unitCost: 10,
                transactionType: InventoryTransactionTypeEnum::OPENING_STOCK->value,
                transactionDate: now(),
                referenceType: null,
                referenceId: null,
                referenceNo: 'CON-FIRST-IN-' . $index,
                batchId: null,
                serialId: null,
                remarks: 'First stock row creation test',
            ));
        }

        $this->assertSame(
            1,
            ProductStock::query()
                ->where('product_id', $this->product->id)
                ->whereNull('product_variant_id')
                ->where('warehouse_id', $this->warehouse->id)
                ->count()
        );

        $this->assertEquals(
            15,
            (float) ProductStock::query()
                ->where('product_id', $this->product->id)
                ->whereNull('product_variant_id')
                ->where('warehouse_id', $this->warehouse->id)
                ->value('quantity')
        );
    }

    public function test_database_rejects_duplicate_non_variant_stock_rows(): void
    {
        ProductStock::create([
            'tenant_id' => $this->tenant->id,
            'product_id' => $this->product->id,
            'product_variant_id' => null,
            'warehouse_id' => $this->warehouse->id,
            'quantity' => 0,
            'average_cost' => 0,
        ]);

        $this->expectException(\Illuminate\Database\QueryException::class);

        ProductStock::create([
            'tenant_id' => $this->tenant->id,
            'product_id' => $this->product->id,
            'product_variant_id' => null,
            'warehouse_id' => $this->warehouse->id,
            'quantity' => 0,
            'average_cost' => 0,
        ]);
    }

    public function test_stock_movement_restores_a_soft_deleted_stock_row(): void
    {
        $stock = ProductStock::create([
            'tenant_id' => $this->tenant->id,
            'product_id' => $this->product->id,
            'product_variant_id' => null,
            'warehouse_id' => $this->warehouse->id,
            'quantity' => 7,
            'average_cost' => 10,
        ]);

        $stock->delete();

        app(InventoryPostingService::class)->stockIn(
            new InventoryMovementData(
                productId: $this->product->id,
                productVariantId: null,
                warehouseId: $this->warehouse->id,
                quantity: 3,
                unitCost: 10,
                transactionType: InventoryTransactionTypeEnum::OPENING_STOCK->value,
                transactionDate: now(),
                referenceType: null,
                referenceId: null,
                referenceNo: 'CON-RESTORE-STOCK',
                batchId: null,
                serialId: null,
                remarks: 'Restore existing stock row',
            )
        );

        $stock->refresh();

        $this->assertFalse($stock->trashed());
        $this->assertEquals(10, (float) $stock->quantity);

        $this->assertSame(
            1,
            ProductStock::withTrashed()
                ->where('product_id', $this->product->id)
                ->whereNull('product_variant_id')
                ->where('warehouse_id', $this->warehouse->id)
                ->count()
        );
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
}