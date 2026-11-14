<?php

namespace Tests\Feature\Inventory;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

use Tests\Support\CreatesTenant;
use Tests\Support\CreatesWarehouse;
use Tests\Support\CreatesProduct;

use App\Core\Exceptions\BusinessException;
use App\Core\Tenant\TenantManager;

use App\Modules\Tenant\Models\Tenant;
use App\Modules\Product\Models\Product;
use App\Modules\Warehouse\Models\Warehouse;
use App\Modules\Accounting\Services\AccountingSetupService;
use App\Modules\Settings\Models\Setting;

use App\Modules\Inventory\Data\InventoryMovementData;
use App\Modules\Inventory\Models\InventoryCostLayer;
use App\Modules\Inventory\Models\ProductStock;
use App\Modules\Inventory\Services\InventoryPostingService;

class InventoryFifoHistoricalLayerEligibilityTest extends TestCase
{
    use RefreshDatabase;
    use CreatesTenant;
    use CreatesWarehouse;
    use CreatesProduct;

    protected Tenant $tenant;

    protected Product $product;

    protected Warehouse $warehouse;

    protected InventoryPostingService $postingService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = $this->createTestTenant();

        app(TenantManager::class)->setTenant($this->tenant);

        app(AccountingSetupService::class)->setup($this->tenant);

        Setting::updateOrCreate(
            [
                'tenant_id' => $this->tenant->id,
                'group' => 'inventory',
                'key' => 'costing_method',
            ],
            [
                'value' => 'FIFO',
            ]
        );

        $this->warehouse = $this->createWarehouse();
        $this->product = $this->createProduct();
        $this->postingService = app(InventoryPostingService::class);
    }

    public function test_backdated_stock_out_cannot_consume_a_future_fifo_layer(): void
    {
        $receipt = $this->postingService->stockIn(
            new InventoryMovementData(
                productId: $this->product->id,
                productVariantId: null,
                warehouseId: $this->warehouse->id,
                quantity: 10,
                unitCost: 25,
                transactionType: 'PURCHASE',
                transactionDate: now()->addDay(),
                referenceNo: 'PUR-FUTURE-001',
            )
        );

        $layer = InventoryCostLayer::query()
            ->where('stock_ledger_id', $receipt->id)
            ->firstOrFail();

        try {
            $this->postingService->stockOut(
                new InventoryMovementData(
                    productId: $this->product->id,
                    productVariantId: null,
                    warehouseId: $this->warehouse->id,
                    quantity: 5,
                    unitCost: 0,
                    transactionType: 'SALE',
                    transactionDate: now(),
                    referenceNo: 'SALE-BACKDATED-001',
                )
            );

            $this->fail('A backdated sale must not consume a future FIFO layer.');
        } catch (BusinessException $exception) {
            $this->assertStringContainsString(
                'Insufficient FIFO stock layers',
                $exception->getMessage()
            );
        }

        $layer->refresh();

        $this->assertSame('10.0000', (string) $layer->remaining_quantity);
        $this->assertDatabaseCount('stock_ledgers', 1);
        $this->assertDatabaseCount('inventory_cost_layer_consumptions', 0);

        $stock = ProductStock::query()
            ->where('product_id', $this->product->id)
            ->where('warehouse_id', $this->warehouse->id)
            ->firstOrFail();

        $this->assertSame('10.0000', (string) $stock->quantity);
    }

    public function test_fifo_layer_received_on_transaction_date_is_eligible(): void
    {
        $transactionDate = now()->startOfDay();

        $receipt = $this->postingService->stockIn(
            new InventoryMovementData(
                productId: $this->product->id,
                productVariantId: null,
                warehouseId: $this->warehouse->id,
                quantity: 10,
                unitCost: 25,
                transactionType: 'PURCHASE',
                transactionDate: $transactionDate,
                referenceNo: 'PUR-SAME-DAY-001',
            )
        );

        $sale = $this->postingService->stockOut(
            new InventoryMovementData(
                productId: $this->product->id,
                productVariantId: null,
                warehouseId: $this->warehouse->id,
                quantity: 4,
                unitCost: 0,
                transactionType: 'SALE',
                transactionDate: now()->endOfDay(),
                referenceNo: 'SALE-SAME-DAY-001',
            )
        );

        $layer = InventoryCostLayer::query()
            ->where('stock_ledger_id', $receipt->id)
            ->firstOrFail();

        $this->assertSame('6.0000', (string) $layer->remaining_quantity);
        $this->assertEquals(100, (float) $sale->total_cost);
        $this->assertDatabaseHas('inventory_cost_layer_consumptions', [
            'stock_ledger_id' => $sale->id,
            'inventory_cost_layer_id' => $layer->id,
            'quantity' => '4.0000',
        ]);
    }
}
