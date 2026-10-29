<?php

namespace Tests\Feature\PurchaseReturn;

use App\Core\Tenant\TenantManager;
use App\Modules\Accounting\Services\AccountingSetupService;
use App\Modules\Inventory\Enums\ProductSerialStatusEnum;
use App\Modules\Inventory\Models\ProductBatch;
use App\Modules\Inventory\Models\ProductSerial;
use App\Modules\Inventory\Models\ProductStock;
use App\Modules\Product\Enums\InventoryTrackingTypeEnum;
use App\Modules\Product\Enums\ProductTypeEnum;
use App\Modules\Product\Models\Product;
use App\Modules\Purchase\Models\Purchase;
use App\Modules\Purchase\Services\PurchaseService;
use App\Modules\Supplier\Models\Supplier;
use App\Modules\Tenant\Models\Tenant;
use App\Modules\User\Models\User;
use App\Modules\Warehouse\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Support\CreatesTenant;
use Tests\Support\CreatesUser;
use Tests\TestCase;

class PurchaseReturnBatchSerialTest extends TestCase
{
    use RefreshDatabase, CreatesTenant, CreatesUser;

    protected Tenant $tenant;
    protected User $user;
    protected Supplier $supplier;
    protected Warehouse $warehouse;
    protected Product $batchProduct;
    protected Product $serialProduct;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = $this->createTestTenant();
        $this->user = $this->createTestUser();
        Sanctum::actingAs($this->user);

        app(AccountingSetupService::class)->setup($this->tenant);

        $this->supplier = Supplier::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Tracking Supplier Co',
            'is_active' => true,
        ]);

        $this->warehouse = Warehouse::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Tracking Warehouse',
            'code' => 'WH-TRACK',
            'is_active' => true,
        ]);

        $this->batchProduct = Product::create([
            'tenant_id' => $this->tenant->id,
            'code' => 'PRD-BATCH-01',
            'sku' => 'SKU-BATCH-01',
            'name' => 'Batch Tracked Product',
            'slug' => 'batch-tracked-product',
            'product_type' => ProductTypeEnum::PRODUCT,
            'inventory_tracking_type' => InventoryTrackingTypeEnum::BATCH,
            'is_active' => true,
            'purchase_price' => 120,
            'selling_price' => 180,
        ]);

        $this->serialProduct = Product::create([
            'tenant_id' => $this->tenant->id,
            'code' => 'PRD-SERIAL-01',
            'sku' => 'SKU-SERIAL-01',
            'name' => 'Serial Tracked Product',
            'slug' => 'serial-tracked-product',
            'product_type' => ProductTypeEnum::PRODUCT,
            'inventory_tracking_type' => InventoryTrackingTypeEnum::SERIAL,
            'is_active' => true,
            'purchase_price' => 500,
            'selling_price' => 700,
        ]);
    }

    public function test_can_return_batch_tracked_items_and_decreases_batch_stock(): void
    {
        $purchaseService = app(PurchaseService::class);
        $purchase = $purchaseService->create([
            'supplier_uuid' => $this->supplier->uuid,
            'warehouse_uuid' => $this->warehouse->uuid,
            'purchase_date' => now()->toDateString(),
            'items' => [
                [
                    'product_uuid' => $this->batchProduct->uuid,
                    'quantity' => 10,
                    'unit_cost' => 120,
                    'batches' => [
                        [
                            'batch_no' => 'LOT-2026-X',
                            'quantity' => 10,
                            'expiry_date' => now()->addMonths(6)->toDateString(),
                        ],
                    ],
                ],
            ],
        ]);
        $purchase = $purchaseService->confirm($purchase);

        $batch = ProductBatch::query()->where('batch_no', 'LOT-2026-X')->first();
        $this->assertNotNull($batch);
        $this->assertEquals(10, (float) $batch->remaining_quantity);

        $purchaseItem = $purchase->items->first();

        $res = $this->postJson('/api/purchase-returns', [
            'purchase_uuid' => $purchase->uuid,
            'return_date' => now()->toDateString(),
            'return_reason' => 'Defective batch lot',
            'items' => [
                [
                    'purchase_item_uuid' => $purchaseItem->uuid,
                    'returned_quantity' => 4,
                    'unit_cost' => 120,
                    'batches' => [
                        [
                            'batch_no' => 'LOT-2026-X',
                            'quantity' => 4,
                        ],
                    ],
                ],
            ],
        ]);
        $res->assertStatus(201);
        $returnUuid = $res->json('data.uuid');

        $confirmRes = $this->postJson("/api/purchase-returns/{$returnUuid}/confirm");
        $confirmRes->assertStatus(200);

        app(TenantManager::class)->setTenant($this->tenant);

        $batch->refresh();
        $this->assertEquals(6, (float) $batch->remaining_quantity);

        $stock = ProductStock::query()
            ->where('product_id', $this->batchProduct->id)
            ->where('warehouse_id', $this->warehouse->id)
            ->first();
        $this->assertEquals(6, (float) $stock->quantity);
    }

    public function test_can_return_serial_tracked_items_and_marks_serials_returned(): void
    {
        $purchaseService = app(PurchaseService::class);
        $purchase = $purchaseService->create([
            'supplier_uuid' => $this->supplier->uuid,
            'warehouse_uuid' => $this->warehouse->uuid,
            'purchase_date' => now()->toDateString(),
            'items' => [
                [
                    'product_uuid' => $this->serialProduct->uuid,
                    'quantity' => 3,
                    'unit_cost' => 500,
                    'serials' => ['SR-001', 'SR-002', 'SR-003'],
                ],
            ],
        ]);
        $purchase = $purchaseService->confirm($purchase);

        $this->assertEquals(3, ProductSerial::query()->where('status', ProductSerialStatusEnum::AVAILABLE)->count());

        $purchaseItem = $purchase->items->first();

        $res = $this->postJson('/api/purchase-returns', [
            'purchase_uuid' => $purchase->uuid,
            'return_date' => now()->toDateString(),
            'return_reason' => 'Screen damage',
            'items' => [
                [
                    'purchase_item_uuid' => $purchaseItem->uuid,
                    'returned_quantity' => 2,
                    'unit_cost' => 500,
                    'serials' => ['SR-001', 'SR-002'],
                ],
            ],
        ]);
        $res->assertStatus(201);
        $returnUuid = $res->json('data.uuid');

        $confirmRes = $this->postJson("/api/purchase-returns/{$returnUuid}/confirm");
        $confirmRes->assertStatus(200);

        app(TenantManager::class)->setTenant($this->tenant);

        $s1 = ProductSerial::query()->where('serial_number', 'SR-001')->first();
        $s2 = ProductSerial::query()->where('serial_number', 'SR-002')->first();
        $s3 = ProductSerial::query()->where('serial_number', 'SR-003')->first();

        $this->assertEquals(ProductSerialStatusEnum::RETURNED, $s1->status);
        $this->assertEquals(ProductSerialStatusEnum::RETURNED, $s2->status);
        $this->assertEquals(ProductSerialStatusEnum::AVAILABLE, $s3->status);

        $stock = ProductStock::query()
            ->where('product_id', $this->serialProduct->id)
            ->where('warehouse_id', $this->warehouse->id)
            ->first();
        $this->assertEquals(1, (float) $stock->quantity);
    }
}
