<?php

namespace Tests\Feature\Inventory;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

use Tests\Support\CreatesTenant;
use Tests\Support\CreatesWarehouse;
use Tests\Support\CreatesProduct;

use App\Core\Tenant\TenantManager;
use App\Modules\Accounting\Services\AccountingSetupService;
use App\Modules\Settings\Models\Setting;
use App\Modules\Settings\Enums\SettingGroupEnum;
use App\Modules\Settings\Enums\InventoryCostingMethodEnum;


use App\Modules\Tenant\Models\Tenant;
use App\Modules\Product\Models\Product;
use App\Modules\Warehouse\Models\Warehouse;

use App\Modules\Inventory\Data\InventoryMovementData;
use App\Modules\Inventory\Models\InventoryCostLayer;
use App\Modules\Inventory\Enums\InventoryCostLayerStatusEnum;
use App\Modules\Inventory\Services\InventoryPostingService;
use App\Modules\Inventory\Services\InventoryReversalService;

class InventoryFifoCostLayerTest extends TestCase
{
    use RefreshDatabase;
    use CreatesTenant;
    use CreatesWarehouse;
    use CreatesProduct;

    protected Tenant $tenant;

    protected Product $product;

    protected Warehouse $warehouse;

    protected InventoryPostingService $postingService;

    protected InventoryReversalService $reversalService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = $this->createTestTenant();

        app(TenantManager::class)
            ->setTenant($this->tenant);

        app(AccountingSetupService::class)
            ->setup($this->tenant);

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

        $this->postingService =
            app(InventoryPostingService::class);

        $this->reversalService =
            app(InventoryReversalService::class);
    }

    public function test_stock_in_creates_fifo_cost_layer(): void
    {
        $ledger =
            $this->postingService->stockIn(
                new InventoryMovementData(
                    productId: $this->product->id,
                    productVariantId: null,
                    warehouseId: $this->warehouse->id,
                    quantity: 100,
                    unitCost: 10,
                    transactionType: 'PURCHASE',
                    transactionDate: now(),
                    referenceType: null,
                    referenceId: null,
                    referenceNo: 'PUR-001',
                    batchId: null,
                    serialId: null,
                    remarks: 'FIFO layer',
                )
            );

        $this->assertNotNull($ledger);

        $layer =
            InventoryCostLayer::query()
            ->where(
                'stock_ledger_id',
                $ledger->id
            )
            ->first();

        $this->assertNotNull($layer);

        $this->assertEquals(
            $this->product->id,
            $layer->product_id
        );

        $this->assertEquals(
            $this->warehouse->id,
            $layer->warehouse_id
        );

        $this->assertEquals(
            100,
            (float) $layer->original_quantity
        );

        $this->assertEquals(
            100,
            (float) $layer->remaining_quantity
        );

        $this->assertEquals(
            10,
            (float) $layer->unit_cost
        );

        $this->assertEquals(
            InventoryCostLayerStatusEnum::OPEN,
            $layer->status
        );
    }

    public function test_multiple_stock_in_creates_multiple_fifo_layers(): void
    {
        $this->postingService->stockIn(
            new InventoryMovementData(
                productId: $this->product->id,
                productVariantId: null,
                warehouseId: $this->warehouse->id,
                quantity: 100,
                unitCost: 10,
                transactionType: 'PURCHASE',
                transactionDate: now()->subDay(),
                referenceType: null,
                referenceId: null,
                referenceNo: 'PUR-001',
                batchId: null,
                serialId: null,
                remarks: 'Layer 1',
            )
        );

        $this->postingService->stockIn(
            new InventoryMovementData(
                productId: $this->product->id,
                productVariantId: null,
                warehouseId: $this->warehouse->id,
                quantity: 50,
                unitCost: 20,
                transactionType: 'PURCHASE',
                transactionDate: now(),
                referenceType: null,
                referenceId: null,
                referenceNo: 'PUR-002',
                batchId: null,
                serialId: null,
                remarks: 'Layer 2',
            )
        );

        $layers =
            InventoryCostLayer::query()
            ->where(
                'product_id',
                $this->product->id
            )
            ->where(
                'warehouse_id',
                $this->warehouse->id
            )
            ->orderBy('layer_date')
            ->orderBy('id')
            ->get();

        $this->assertCount(
            2,
            $layers
        );

        $layer1 = $layers->first();
        $layer2 = $layers->last();

        $this->assertEquals(
            100,
            (float) $layer1->original_quantity
        );

        $this->assertEquals(
            100,
            (float) $layer1->remaining_quantity
        );

        $this->assertEquals(
            10,
            (float) $layer1->unit_cost
        );

        $this->assertEquals(
            InventoryCostLayerStatusEnum::OPEN,
            $layer1->status
        );

        $this->assertEquals(
            50,
            (float) $layer2->original_quantity
        );

        $this->assertEquals(
            50,
            (float) $layer2->remaining_quantity
        );

        $this->assertEquals(
            20,
            (float) $layer2->unit_cost
        );

        $this->assertEquals(
            InventoryCostLayerStatusEnum::OPEN,
            $layer2->status
        );
    }

    public function test_reverse_stock_in_marks_fifo_layer_as_reversed(): void
    {
        $ledger =
            $this->postingService->stockIn(
                new InventoryMovementData(
                    productId: $this->product->id,
                    productVariantId: null,
                    warehouseId: $this->warehouse->id,
                    quantity: 100,
                    unitCost: 10,
                    transactionType: 'PURCHASE',
                    transactionDate: now(),
                    referenceType: null,
                    referenceId: null,
                    referenceNo: 'PUR-001',
                    batchId: null,
                    serialId: null,
                    remarks: 'FIFO layer',
                )
            );

        $layer =
            InventoryCostLayer::query()
            ->where(
                'stock_ledger_id',
                $ledger->id
            )
            ->first();

        $this->assertNotNull($layer);

        $this->reversalService->reverse(
            new \App\Modules\Inventory\Data\InventoryReversalData(
                stockLedgerId: $ledger->id,
                reversalDate: now(),
                referenceType: null,
                referenceId: null,
                referenceNo: 'REV-001',
                remarks: 'Reverse FIFO layer',
            )
        );

        $layer->refresh();

        $this->assertEquals(
            0,
            (float) $layer->remaining_quantity
        );

        $this->assertEquals(
            InventoryCostLayerStatusEnum::REVERSED,
            $layer->status
        );
    }

    public function test_reversed_fifo_layer_does_not_remain_available(): void
    {
        $ledger =
            $this->postingService->stockIn(
                new InventoryMovementData(
                    productId: $this->product->id,
                    productVariantId: null,
                    warehouseId: $this->warehouse->id,
                    quantity: 25,
                    unitCost: 15,
                    transactionType: 'PURCHASE',
                    transactionDate: now(),
                    referenceType: null,
                    referenceId: null,
                    referenceNo: 'PUR-001',
                    batchId: null,
                    serialId: null,
                    remarks: 'FIFO layer',
                )
            );

        $layer =
            InventoryCostLayer::query()
            ->where(
                'stock_ledger_id',
                $ledger->id
            )
            ->first();

        $this->assertNotNull($layer);

        $this->reversalService->reverse(
            new \App\Modules\Inventory\Data\InventoryReversalData(
                stockLedgerId: $ledger->id,
                reversalDate: now(),
                referenceType: null,
                referenceId: null,
                referenceNo: 'REV-001',
                remarks: 'Reverse FIFO layer',
            )
        );

        $availableLayers =
            InventoryCostLayer::query()
            ->where(
                'product_id',
                $this->product->id
            )
            ->where(
                'warehouse_id',
                $this->warehouse->id
            )
            ->where(
                'status',
                InventoryCostLayerStatusEnum::OPEN
            )
            ->where(
                'remaining_quantity',
                '>',
                0
            )
            ->get();

        $this->assertCount(
            0,
            $availableLayers
        );

        $layer->refresh();

        $this->assertEquals(
            0,
            (float) $layer->remaining_quantity
        );

        $this->assertEquals(
            InventoryCostLayerStatusEnum::REVERSED,
            $layer->status
        );
    }
}
