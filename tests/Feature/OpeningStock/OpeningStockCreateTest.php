<?php

namespace Tests\Feature\OpeningStock;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

use App\Core\Tenant\TenantManager;

use Tests\Support\CreatesTenant;
use Tests\Support\CreatesWarehouse;
use Tests\Support\CreatesProduct;

use App\Modules\Settings\Models\Setting;
use App\Modules\Accounting\Services\AccountingSetupService;

use App\Modules\Tenant\Models\Tenant;
use App\Modules\Product\Models\Product;
use App\Modules\Warehouse\Models\Warehouse;

use App\Modules\OpeningStock\Models\OpeningStock;
use App\Modules\OpeningStock\Services\OpeningStockService;

use App\Modules\Product\Enums\ProductTypeEnum;
use App\Modules\Product\Enums\InventoryTrackingTypeEnum;
use App\Modules\SystemNumber\Enums\SystemNumberTypeEnum;

class OpeningStockCreateTest extends TestCase
{
    use RefreshDatabase;
    use CreatesTenant;
    use CreatesWarehouse;
    use CreatesProduct;

    protected Tenant $tenant;
    protected Warehouse $warehouse;
    protected Product $productA;
    protected Product $productB;
    protected OpeningStockService $service;

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

        $this->productA = $this->createProduct([
            'tenant_id' => $this->tenant->id,
            'name' => 'Opening Product A',
            'code' => nextSystemNumber(
                SystemNumberTypeEnum::PRODUCT
            ),
            'sku' => 'OPEN-001',
            'slug' => 'opening-product-a-' . random_int(10000, 99999),
            'product_type' => ProductTypeEnum::PRODUCT,
            'inventory_tracking_type' => InventoryTrackingTypeEnum::NONE,
            'purchase_price' => 100,
            'selling_price' => 150,
            'is_active' => true,
        ]);

        $this->productB = $this->createProduct([
            'tenant_id' => $this->tenant->id,
            'name' => 'Opening Product B',
            'code' => nextSystemNumber(
                SystemNumberTypeEnum::PRODUCT
            ),
            'sku' => 'OPEN-002',
            'slug' => 'opening-product-b-' . random_int(10000, 99999),
            'product_type' => ProductTypeEnum::PRODUCT,
            'inventory_tracking_type' => InventoryTrackingTypeEnum::NONE,
            'purchase_price' => 200,
            'selling_price' => 250,
            'is_active' => true,
        ]);

        $this->service = app(
            OpeningStockService::class
        );
    }

    public function test_opening_stock_creates_sources_items_and_totals(): void
    {
        $openingStock = $this->service->create([
            'warehouse_id' => $this->warehouse->id,
            'opening_date' => now()->toDateString(),
            'remarks' => 'Opening stock test',

            'sources' => [
                [
                    'supplier_id' => null,
                    'bill_no' => 'OPEN-BILL-001',
                    'bill_date' => now()->toDateString(),
                    'remarks' => 'Opening source',

                    'items' => [
                        [
                            'product_id' => $this->productA->id,
                            'product_variant_id' => null,
                            'quantity' => 10,
                            'unit_cost' => 100,
                            'remarks' => 'Product A',
                        ],
                        [
                            'product_id' => $this->productB->id,
                            'product_variant_id' => null,
                            'quantity' => 5,
                            'unit_cost' => 200,
                            'remarks' => 'Product B',
                        ],
                    ],
                ],
            ],
        ]);

        $this->assertInstanceOf(
            OpeningStock::class,
            $openingStock
        );

        $this->assertNotNull(
            $openingStock->id
        );

        $this->assertEquals(
            $this->tenant->id,
            $openingStock->tenant_id
        );

        $this->assertEquals(
            $this->warehouse->id,
            $openingStock->warehouse_id
        );

        $this->assertEquals(
            15,
            (float) $openingStock->total_quantity
        );

        $this->assertEquals(
            2000,
            (float) $openingStock->total_amount
        );

        $openingStock->load([
            'sources.items',
        ]);

        $this->assertCount(
            1,
            $openingStock->sources
        );

        $source = $openingStock->sources->first();

        $this->assertEquals(
            'OPEN-BILL-001',
            $source->bill_no
        );

        $this->assertCount(
            2,
            $source->items
        );

        $itemA = $source->items->firstWhere(
            'product_id',
            $this->productA->id
        );

        $itemB = $source->items->firstWhere(
            'product_id',
            $this->productB->id
        );

        $this->assertNotNull($itemA);
        $this->assertNotNull($itemB);

        $this->assertEquals(
            $this->tenant->id,
            $itemA->tenant_id
        );

        $this->assertEquals(
            10,
            (float) $itemA->quantity
        );

        $this->assertEquals(
            100,
            (float) $itemA->unit_cost
        );

        $this->assertEquals(
            1000,
            (float) $itemA->total_cost
        );

        $this->assertEquals(
            $this->tenant->id,
            $itemB->tenant_id
        );

        $this->assertEquals(
            5,
            (float) $itemB->quantity
        );

        $this->assertEquals(
            200,
            (float) $itemB->unit_cost
        );

        $this->assertEquals(
            1000,
            (float) $itemB->total_cost
        );

        $this->assertDatabaseHas(
            'opening_stocks',
            [
                'id' => $openingStock->id,
                'tenant_id' => $this->tenant->id,
                'warehouse_id' => $this->warehouse->id,
                'total_quantity' => 15,
                'total_amount' => 2000,
            ]
        );

        $this->assertDatabaseCount(
            'opening_stock_sources',
            1
        );

        $this->assertDatabaseCount(
            'opening_stock_items',
            2
        );

        $this->assertDatabaseHas(
            'opening_stock_items',
            [
                'tenant_id' => $this->tenant->id,
                'opening_stock_source_id' => $source->id,
                'product_id' => $this->productA->id,
                'quantity' => 10,
                'unit_cost' => 100,
                'total_cost' => 1000,
            ]
        );

        $this->assertDatabaseHas(
            'opening_stock_items',
            [
                'tenant_id' => $this->tenant->id,
                'opening_stock_source_id' => $source->id,
                'product_id' => $this->productB->id,
                'quantity' => 5,
                'unit_cost' => 200,
                'total_cost' => 1000,
            ]
        );
    }
}
