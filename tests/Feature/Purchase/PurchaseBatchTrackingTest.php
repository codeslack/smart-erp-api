<?php

namespace Tests\Feature\Purchase;

use App\Modules\Tenant\Models\Tenant;
use App\Modules\User\Models\User;
use App\Modules\Supplier\Models\Supplier;
use App\Modules\Warehouse\Models\Warehouse;
use App\Modules\Product\Models\Product;
use App\Modules\ProductUnit\Models\ProductUnit;
use App\Modules\Unit\Models\Unit;
use App\Modules\Product\Enums\ProductTypeEnum;
use App\Modules\Product\Enums\InventoryTrackingTypeEnum;
use App\Modules\Inventory\Models\ProductBatch;
use App\Modules\Inventory\Enums\ProductBatchStatusEnum;
use App\Modules\Accounting\Services\AccountingSetupService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Support\CreatesTenant;
use Tests\Support\CreatesUser;
use Tests\TestCase;

class PurchaseBatchTrackingTest extends TestCase
{
    use RefreshDatabase;
    use CreatesTenant;
    use CreatesUser;

    protected Tenant $tenant;
    protected User $user;
    protected Supplier $supplier;
    protected Warehouse $warehouse;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = $this->createTestTenant();
        $this->user = $this->createTestUser();
        Sanctum::actingAs($this->user);

        app(AccountingSetupService::class)->setup($this->tenant);

        $this->supplier = Supplier::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Batch Supplier',
            'is_active' => true,
        ]);

        $this->warehouse = Warehouse::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Main Warehouse',
            'code' => 'WH-MAIN',
            'is_active' => true,
        ]);
    }

    public function test_direct_purchase_creates_and_posts_batch_tracked_product(): void
    {
        $batchProduct = Product::create([
            'tenant_id' => $this->tenant->id,
            'code' => 'PRD-BAT',
            'sku' => 'SKU-BAT',
            'name' => 'Batch Medicine',
            'slug' => 'batch-medicine',
            'product_type' => ProductTypeEnum::PRODUCT,
            'inventory_tracking_type' => InventoryTrackingTypeEnum::BATCH,
            'has_expiry' => true,
            'is_active' => true,
            'purchase_price' => 50,
            'selling_price' => 80,
        ]);

        $payload = [
            'supplier_uuid' => $this->supplier->uuid,
            'warehouse_uuid' => $this->warehouse->uuid,
            'purchase_date' => now()->toDateString(),
            'items' => [
                [
                    'product_uuid' => $batchProduct->uuid,
                    'quantity' => 10,
                    'unit_cost' => 50,
                    'batches' => [
                        [
                            'batch_no' => 'LOT-2026-A',
                            'quantity' => 6,
                            'expiry_date' => '2028-06-30',
                        ],
                        [
                            'batch_no' => 'LOT-2026-B',
                            'quantity' => 4,
                            'expiry_date' => '2028-12-31',
                        ],
                    ],
                ],
            ],
        ];

        $response = $this->postJson(route('purchases.store'), $payload);
        $response->assertStatus(201);

        $purchaseUuid = $response->json('data.uuid');
        $this->assertDatabaseHas('purchase_item_batches', [
            'batch_no' => 'LOT-2026-A',
            'quantity' => 6,
        ]);
        $this->assertDatabaseHas('purchase_item_batches', [
            'batch_no' => 'LOT-2026-B',
            'quantity' => 4,
        ]);

        $confirmResponse = $this->postJson("/api/purchases/{$purchaseUuid}/confirm");
        $confirmResponse->assertStatus(200);

        $this->assertDatabaseHas('product_batches', [
            'tenant_id' => $this->tenant->id,
            'batch_no' => 'LOT-2026-A',
            'status' => ProductBatchStatusEnum::ACTIVE->value,
            'remaining_quantity' => 6,
        ]);
        $this->assertDatabaseHas('product_batches', [
            'tenant_id' => $this->tenant->id,
            'batch_no' => 'LOT-2026-B',
            'status' => ProductBatchStatusEnum::ACTIVE->value,
            'remaining_quantity' => 4,
        ]);

        $batchA = ProductBatch::withoutGlobalScopes()->where('batch_no', 'LOT-2026-A')->first();
        $this->assertNotNull($batchA);
        $this->assertEquals(ProductBatchStatusEnum::ACTIVE, $batchA->status);
        $this->assertEquals(6, $batchA->remaining_quantity);

        $this->assertDatabaseHas('stock_ledgers', [
            'product_id' => $batchProduct->id,
            'product_batch_id' => $batchA->id,
            'quantity_in' => 6,
        ]);
    }

    public function test_batch_purchase_converts_document_units_to_base_units(): void
    {
        $baseUnit = Unit::create([
            'tenant_id' => $this->tenant->id,
            'code' => 'BPCS',
            'name' => 'Batch Piece',
            'short_name' => 'bpc',
            'is_active' => true,
        ]);

        $boxUnit = Unit::create([
            'tenant_id' => $this->tenant->id,
            'code' => 'BBOX',
            'name' => 'Batch Box',
            'short_name' => 'bbox',
            'is_active' => true,
        ]);

        $batchProduct = Product::create([
            'tenant_id' => $this->tenant->id,
            'unit_id' => $baseUnit->id,
            'code' => 'PRD-BAT-UNIT',
            'sku' => 'SKU-BAT-UNIT',
            'name' => 'Batch Unit Conversion',
            'slug' => 'batch-unit-conversion',
            'product_type' => ProductTypeEnum::PRODUCT,
            'inventory_tracking_type' => InventoryTrackingTypeEnum::BATCH,
            'has_expiry' => true,
            'is_active' => true,
            'purchase_price' => 50,
            'selling_price' => 80,
        ]);

        ProductUnit::create([
            'product_id' => $batchProduct->id,
            'unit_id' => $baseUnit->id,
            'conversion_factor' => 1,
            'is_purchase_unit' => true,
            'is_sales_unit' => true,
            'is_active' => true,
        ]);

        ProductUnit::create([
            'product_id' => $batchProduct->id,
            'unit_id' => $boxUnit->id,
            'conversion_factor' => 10,
            'is_purchase_unit' => true,
            'is_sales_unit' => true,
            'is_active' => true,
        ]);

        $payload = [
            'supplier_uuid' => $this->supplier->uuid,
            'warehouse_uuid' => $this->warehouse->uuid,
            'purchase_date' => now()->toDateString(),
            'items' => [
                [
                    'product_uuid' => $batchProduct->uuid,
                    'unit_uuid' => $boxUnit->uuid,
                    'quantity' => 2,
                    'unit_cost' => 500,
                    'batches' => [
                        [
                            'batch_no' => 'LOT-UNIT-A',
                            'quantity' => 1,
                            'expiry_date' => '2028-06-30',
                        ],
                        [
                            'batch_no' => 'LOT-UNIT-B',
                            'quantity' => 1,
                            'expiry_date' => '2028-12-31',
                        ],
                    ],
                ],
            ],
        ];

        $createResponse = $this->postJson(route('purchases.store'), $payload);

        $createResponse
            ->assertCreated()
            ->assertJsonPath('data.items.0.base_quantity', 20)
            ->assertJsonPath('data.items.0.base_unit_cost', 50);

        $purchaseUuid = $createResponse->json('data.uuid');

        $this->assertDatabaseHas('purchase_item_batches', [
            'batch_no' => 'LOT-UNIT-A',
            'quantity' => 1,
            'base_quantity' => 10,
        ]);

        $this->postJson("/api/purchases/{$purchaseUuid}/confirm")
            ->assertOk();

        $this->assertDatabaseHas('product_batches', [
            'batch_no' => 'LOT-UNIT-A',
            'remaining_quantity' => 10,
            'unit_cost' => 50,
        ]);

        $this->assertDatabaseHas('stock_ledgers', [
            'product_id' => $batchProduct->id,
            'quantity_in' => 10,
            'unit_cost' => 50,
        ]);
    }

    public function test_batch_tracked_product_fails_validation_if_batch_sum_mismatches_quantity(): void
    {
        $batchProduct = Product::create([
            'tenant_id' => $this->tenant->id,
            'code' => 'PRD-BAT-ERR',
            'sku' => 'SKU-BAT-ERR',
            'name' => 'Batch Medicine Error',
            'slug' => 'batch-medicine-error',
            'product_type' => ProductTypeEnum::PRODUCT,
            'inventory_tracking_type' => InventoryTrackingTypeEnum::BATCH,
            'is_active' => true,
            'purchase_price' => 50,
            'selling_price' => 80,
        ]);

        $payload = [
            'supplier_uuid' => $this->supplier->uuid,
            'warehouse_uuid' => $this->warehouse->uuid,
            'purchase_date' => now()->toDateString(),
            'items' => [
                [
                    'product_uuid' => $batchProduct->uuid,
                    'quantity' => 10,
                    'unit_cost' => 50,
                    'batches' => [
                        [
                            'batch_no' => 'LOT-ERR-1',
                            'quantity' => 7,
                        ],
                    ],
                ],
            ],
        ];

        $response = $this->postJson(route('purchases.store'), $payload);
        $response->assertStatus(422);
    }

    public function test_client_cannot_override_batch_base_quantity(): void
    {
        $batchProduct = Product::create([
            'tenant_id' => $this->tenant->id,
            'code' => 'PRD-BAT-BASE',
            'sku' => 'SKU-BAT-BASE',
            'name' => 'Batch Base Quantity Protection',
            'slug' => 'batch-base-quantity-protection',
            'product_type' => ProductTypeEnum::PRODUCT,
            'inventory_tracking_type' => InventoryTrackingTypeEnum::BATCH,
            'is_active' => true,
            'purchase_price' => 50,
            'selling_price' => 80,
        ]);

        $payload = [
            'supplier_uuid' => $this->supplier->uuid,
            'warehouse_uuid' => $this->warehouse->uuid,
            'purchase_date' => now()->toDateString(),
            'items' => [
                [
                    'product_uuid' => $batchProduct->uuid,
                    'quantity' => 1,
                    'unit_cost' => 50,
                    'batches' => [
                        [
                            'batch_no' => 'FORGED-BASE-BATCH',
                            'quantity' => 1,
                            'base_quantity' => 500,
                        ],
                    ],
                ],
            ],
        ];

        $this->postJson(route('purchases.store'), $payload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors('items.0.batches.0');
    }

    public function test_purchase_show_includes_batches(): void
    {
        $batchProduct = Product::create([
            'tenant_id' => $this->tenant->id,
            'code' => 'PRD-BAT-SHOW',
            'sku' => 'SKU-BAT-SHOW',
            'name' => 'Batch Show Item',
            'slug' => 'batch-show-item',
            'product_type' => ProductTypeEnum::PRODUCT,
            'inventory_tracking_type' => InventoryTrackingTypeEnum::BATCH,
            'is_active' => true,
            'purchase_price' => 30,
            'selling_price' => 60,
        ]);

        $payload = [
            'supplier_uuid' => $this->supplier->uuid,
            'warehouse_uuid' => $this->warehouse->uuid,
            'purchase_date' => now()->toDateString(),
            'items' => [
                [
                    'product_uuid' => $batchProduct->uuid,
                    'quantity' => 5,
                    'unit_cost' => 30,
                    'batches' => [
                        [
                            'batch_no' => 'SHOW-BATCH-1',
                            'quantity' => 5,
                        ],
                    ],
                ],
            ],
        ];

        $createResponse = $this->postJson(route('purchases.store'), $payload);
        $createResponse->assertStatus(201);
        $purchaseUuid = $createResponse->json('data.uuid');

        $showResponse = $this->getJson("/api/purchases/{$purchaseUuid}");
        $showResponse->assertStatus(200);

        $showResponse->assertJsonPath('data.items.0.batches.0.batch_no', 'SHOW-BATCH-1');
        $showResponse->assertJsonPath('data.items.0.batches.0.quantity', 5);
    }
}

