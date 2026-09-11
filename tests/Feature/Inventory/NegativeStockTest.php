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

use App\Modules\Inventory\Data\InventoryMovementData;
use App\Modules\Inventory\Services\InventoryPostingService;

use App\Modules\Settings\Services\SettingService;
use App\Modules\Settings\Enums\SettingGroupEnum;

use App\Core\Exceptions\BusinessException;

class NegativeStockTest extends TestCase
{
    use RefreshDatabase;
    use CreatesTenant;

    protected Tenant $tenant;

    protected Product $product;

    protected Warehouse $warehouse;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant =
            $this->createTestTenant();

        app(TenantManager::class)
            ->setTenant(
                $this->tenant
            );

        $this->product =
            Product::create([
                'tenant_id' =>
                    $this->tenant->id,

                'name' =>
                    'Test Product',

                'code' =>
                    nextSystemNumber(
                        SystemNumberTypeEnum::PRODUCT
                    ),

                'sku' =>
                    'NEG-STOCK-001',

                'slug' =>
                    'neg-stock-' . random_int(
                        10000,
                        99999
                    ),

                'product_type' =>
                    ProductTypeEnum::PRODUCT,

                'inventory_tracking_type' =>
                    InventoryTrackingTypeEnum::NONE,

                'purchase_price' =>
                    100,

                'selling_price' =>
                    150,

                'is_active' =>
                    true,
            ]);

        $this->warehouse =
            Warehouse::create([
                'tenant_id' =>
                    $this->tenant->id,

                'name' =>
                    'Main Warehouse',

                'code' =>
                    nextSystemNumber(
                        SystemNumberTypeEnum::WAREHOUSE
                    ),

                'is_active' =>
                    true,
            ]);

        /*
        |--------------------------------------------------------------
        | Opening Stock
        |--------------------------------------------------------------
        */

        app(
            InventoryPostingService::class
        )->stockIn(
            new InventoryMovementData(
                productId:
                    $this->product->id,

                productVariantId:
                    null,

                warehouseId:
                    $this->warehouse->id,

                quantity:
                    10,

                unitCost:
                    100,

                transactionType:
                    'OPENING_STOCK',

                transactionDate:
                    now(),

                referenceType:
                    null,

                referenceId:
                    null,

                referenceNo:
                    'OPEN-001',

                batchId:
                    null,

                serialId:
                    null,

                remarks:
                    'Opening stock'
            )
        );
    }

    public function test_negative_stock_not_allowed(): void
    {
        app(SettingService::class)
            ->set(
                SettingGroupEnum::INVENTORY->value,
                'allow_negative_stock',
                false
            );

        $this->expectException(
            BusinessException::class
        );

        app(
            InventoryPostingService::class
        )->stockOut(
            new InventoryMovementData(
                productId:
                    $this->product->id,

                productVariantId:
                    null,

                warehouseId:
                    $this->warehouse->id,

                quantity:
                    20,

                unitCost:
                    0,

                transactionType:
                    'SALE',

                transactionDate:
                    now(),

                referenceType:
                    null,

                referenceId:
                    null,

                referenceNo:
                    'SALE-001',

                batchId:
                    null,

                serialId:
                    null,

                remarks:
                    'Negative stock test'
            )
        );
    }

    public function test_negative_stock_allowed(): void
    {
        app(SettingService::class)
            ->set(
                SettingGroupEnum::INVENTORY->value,
                'allow_negative_stock',
                true
            );

        $ledger =
            app(
                InventoryPostingService::class
            )->stockOut(
                new InventoryMovementData(
                    productId:
                        $this->product->id,

                    productVariantId:
                        null,

                    warehouseId:
                        $this->warehouse->id,

                    quantity:
                        20,

                    unitCost:
                        0,

                    transactionType:
                        'SALE',

                    transactionDate:
                        now(),

                    referenceType:
                        null,

                    referenceId:
                        null,

                    referenceNo:
                        'SALE-002',

                    batchId:
                        null,

                    serialId:
                        null,

                    remarks:
                        'Negative stock allowed'
                )
            );

        $this->assertEquals(
            -10,
            (float) $ledger->balance_quantity
        );
    }
}