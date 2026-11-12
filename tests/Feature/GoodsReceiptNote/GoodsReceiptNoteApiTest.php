<?php

namespace Tests\Feature\GoodsReceiptNote;

use Tests\TestCase;

use Illuminate\Foundation\Testing\RefreshDatabase;

use Tests\Support\CreatesTenant;
use Tests\Support\CreatesProduct;
use Tests\Support\CreatesWarehouse;
use Tests\Support\CreatesUser;

use Laravel\Sanctum\Sanctum;

use App\Core\Tenant\TenantManager;

use App\Modules\User\Models\User;
use App\Modules\Tenant\Models\Tenant;
use App\Modules\Product\Models\Product;
use App\Modules\Warehouse\Models\Warehouse;
use App\Modules\Supplier\Models\Supplier;
use App\Modules\Unit\Models\Unit;
use App\Modules\ProductUnit\Models\ProductUnit;

use App\Modules\Accounting\Services\AccountingSetupService;
use App\Modules\Tenant\Services\TenantSetupService;
use App\Modules\ProductUnit\Services\ProductUnitService;

use App\Modules\PurchaseOrder\Models\PurchaseOrder;
use App\Modules\PurchaseOrder\Models\PurchaseOrderItem;

use App\Modules\Inventory\Models\ProductStock;
use App\Modules\Inventory\Models\StockLedger;
use App\Modules\Inventory\Enums\InventoryTransactionTypeEnum;

use App\Modules\Inventory\Models\ProductBatch;
use App\Modules\Inventory\Enums\ProductBatchStatusEnum;
use App\Modules\Product\Enums\InventoryTrackingTypeEnum;

use App\Modules\GoodsReceiptNote\Services\GoodsReceiptNoteService;
use App\Modules\GoodsReceiptNote\Models\GoodsReceiptNote;

class GoodsReceiptNoteApiTest extends TestCase
{
    use RefreshDatabase;
    use CreatesTenant;
    use CreatesUser;
    use CreatesProduct;
    use CreatesWarehouse;

    protected Tenant $tenant;

    protected User $user;

    protected Product $product;

    protected Warehouse $warehouse;

    protected Supplier $supplier;

    protected Unit $baseUnit;

    protected Unit $purchaseUnit;

    protected ProductUnit $purchaseProductUnit;

    protected GoodsReceiptNoteService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = $this->createTestTenant();

        app(TenantManager::class)
            ->setTenant($this->tenant);

        app(AccountingSetupService::class)
            ->setup($this->tenant);

        app(TenantSetupService::class)
            ->setup($this->tenant);

        $this->user = $this->createTestUser();

        Sanctum::actingAs($this->user);

        $this->warehouse = $this->createWarehouse();

        $this->product = $this->createProduct();

        $this->supplier = Supplier::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'API Test Supplier',
            'is_active' => true,
        ]);

        $units = Unit::query()
            ->orderBy('id')
            ->take(2)
            ->get();

        $this->assertCount(
            2,
            $units,
            'Tenant setup must provide at least two units.'
        );

        $this->baseUnit = $units->get(0);
        $this->purchaseUnit = $units->get(1);

        $this->product->update([
            'unit_id' => $this->baseUnit->id,
        ]);

        $productUnitService = app(ProductUnitService::class);

        $productUnitService->syncBaseUnit(
            product: $this->product
        );

        // 1 purchase unit = 10 base units.
        $this->purchaseProductUnit = ProductUnit::create([
            'tenant_id' => $this->tenant->id,
            'product_id' => $this->product->id,
            'unit_id' => $this->purchaseUnit->id,
            'conversion_factor' => 10,
            'is_purchase_unit' => true,
            'is_sales_unit' => false,
            'is_active' => true,
        ]);

        $this->service = app(GoodsReceiptNoteService::class);
    }

    public function test_api_creates_draft_grn(): void
    {
        $purchaseOrderItem = $this->createPurchaseOrderItem();

        $response = $this->postJson(
            '/api/goods-receipt-notes',
            $this->grnPayload($purchaseOrderItem)
        );

        $response
            ->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath(
                'message',
                'Goods receipt note created successfully.'
            )
            ->assertJsonPath('data.status', 'draft')
            ->assertJsonPath(
                'data.purchase_order.uuid',
                $purchaseOrderItem->purchaseOrder->uuid
            );

        $uuid = $response->json('data.uuid');

        // Restore tenant context after the API middleware clears it.
        app(TenantManager::class)->setTenant($this->tenant);

        $this->assertNotEmpty($uuid);

        $this->assertDatabaseHas('goods_receipt_notes', [
            'uuid' => $uuid,
            'tenant_id' => $this->tenant->id,
            'status' => 'draft',
        ]);

        $this->assertDatabaseHas('goods_receipt_note_items', [
            'goods_receipt_note_id' => GoodsReceiptNote::query()
                ->where('uuid', $uuid)
                ->value('id'),
            'product_id' => $this->product->id,
            'purchase_order_item_id' => $purchaseOrderItem->id,
        ]);
    }

    public function test_api_retrieves_grn_using_uuid(): void
    {
        $purchaseOrderItem = $this->createPurchaseOrderItem();

        $grn = $this->service->create(
            $this->grnPayload($purchaseOrderItem)
        );

        $response = $this->getJson(
            '/api/goods-receipt-notes/' . $grn->uuid
        );

        $response
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.uuid', $grn->uuid)
            ->assertJsonPath('data.status', 'draft')
            ->assertJsonPath(
                'data.purchase_order.uuid',
                $purchaseOrderItem->purchaseOrder->uuid
            );

        $this->assertNotEmpty(
            $response->json('data.items')
        );
    }

    public function test_api_updates_draft_grn(): void
    {
        $purchaseOrderItem = $this->createPurchaseOrderItem();

        $grn = $this->service->create(
            $this->grnPayload($purchaseOrderItem)
        );

        $response = $this->putJson(
            '/api/goods-receipt-notes/' . $grn->uuid,
            [
                'remarks' => 'Updated through the API',
                'delivery_challan_number' => 'DC-API-001',
                'vehicle_number' => 'WB-01-API-001',
            ]
        );

        $response
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.uuid', $grn->uuid)
            ->assertJsonPath(
                'data.remarks',
                'Updated through the API'
            )
            ->assertJsonPath(
                'data.delivery_challan_number',
                'DC-API-001'
            )
            ->assertJsonPath(
                'data.vehicle_number',
                'WB-01-API-001'
            );

        $this->assertDatabaseHas('goods_receipt_notes', [
            'id' => $grn->id,
            'remarks' => 'Updated through the API',
            'delivery_challan_number' => 'DC-API-001',
            'vehicle_number' => 'WB-01-API-001',
        ]);
    }

    public function test_api_rejects_invalid_uuid_format(): void
    {
        $this->getJson(
            '/api/goods-receipt-notes/not-a-valid-uuid'
        )->assertNotFound();
    }

    public function test_api_confirms_draft_grn_without_posting_stock(): void
    {
        $purchaseOrderItem = $this->createPurchaseOrderItem(
            status: 'confirmed'
        );

        $grn = $this->service->create(
            $this->grnPayload($purchaseOrderItem)
        );

        $response = $this->postJson(
            '/api/goods-receipt-notes/' . $grn->uuid . '/confirm'
        );

        $response
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.uuid', $grn->uuid)
            ->assertJsonPath('data.status', 'confirmed');

        app(TenantManager::class)->setTenant($this->tenant);

        $this->assertDatabaseHas('goods_receipt_notes', [
            'id' => $grn->id,
            'status' => 'confirmed',
        ]);

        // GRN records receipt progress; stock is posted by Purchase approval.
        $this->assertSame(
            0,
            ProductStock::query()
                ->where('product_id', $this->product->id)
                ->where('warehouse_id', $this->warehouse->id)
                ->whereNull('product_variant_id')
                ->count()
        );

        $this->assertSame(
            0,
            StockLedger::query()
                ->where('referenceable_type', GoodsReceiptNote::class)
                ->where('referenceable_id', $grn->id)
                ->count()
        );

        $this->assertEquals(
            2,
            (float) $purchaseOrderItem->fresh()->received_quantity
        );
    }

    public function test_api_cancels_confirmed_grn_without_posting_stock(): void
    {
        $purchaseOrderItem = $this->createPurchaseOrderItem(
            status: 'confirmed'
        );

        $grn = $this->service->create(
            $this->grnPayload($purchaseOrderItem)
        );

        $this->postJson(
            '/api/goods-receipt-notes/' . $grn->uuid . '/confirm'
        )
            ->assertOk()
            ->assertJsonPath('data.status', 'confirmed');

        app(TenantManager::class)->setTenant($this->tenant);

        $this->assertSame(
            0,
            StockLedger::query()
                ->where('referenceable_type', GoodsReceiptNote::class)
                ->where('referenceable_id', $grn->id)
                ->count()
        );

        $response = $this->postJson(
            '/api/goods-receipt-notes/' . $grn->uuid . '/cancel'
        );

        $response
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.uuid', $grn->uuid)
            ->assertJsonPath('data.status', 'cancelled');

        app(TenantManager::class)->setTenant($this->tenant);

        $this->assertDatabaseHas('goods_receipt_notes', [
            'id' => $grn->id,
            'status' => 'cancelled',
        ]);

        $this->assertSame(
            0,
            StockLedger::query()
                ->where('referenceable_type', GoodsReceiptNote::class)
                ->where('referenceable_id', $grn->id)
                ->count()
        );

        $this->assertSame(
            0,
            ProductStock::query()
                ->where('product_id', $this->product->id)
                ->where('warehouse_id', $this->warehouse->id)
                ->whereNull('product_variant_id')
                ->count()
        );

        $this->assertEquals(
            0,
            (float) $purchaseOrderItem->fresh()->received_quantity
        );
    }

    public function test_cancelling_unbilled_grn_does_not_create_or_restore_batches(): void
    {
        $this->product->update([
            'inventory_tracking_type' => InventoryTrackingTypeEnum::BATCH,
        ]);

        $purchaseOrderItem = $this->createPurchaseOrderItem(
            status: 'confirmed'
        );

        $payload = $this->grnPayload($purchaseOrderItem);

        $payload['items'][0]['batches'] = [
            [
                'batch_no' => 'GRN-CANCEL-BATCH-001',
                'quantity' => 20,
            ],
        ];

        $grn = $this->service->create($payload);

        $this->postJson(
            '/api/goods-receipt-notes/' . $grn->uuid . '/confirm'
        )
            ->assertOk()
            ->assertJsonPath('data.status', 'confirmed');

        app(TenantManager::class)->setTenant($this->tenant);

        $this->assertDatabaseMissing('product_batches', [
            'batch_no' => 'GRN-CANCEL-BATCH-001',
            'product_id' => $this->product->id,
            'warehouse_id' => $this->warehouse->id,
        ]);

        $this->postJson(
            '/api/goods-receipt-notes/' . $grn->uuid . '/cancel'
        )
            ->assertOk()
            ->assertJsonPath('data.status', 'cancelled');

        $this->assertDatabaseMissing('product_batches', [
            'batch_no' => 'GRN-CANCEL-BATCH-001',
            'product_id' => $this->product->id,
            'warehouse_id' => $this->warehouse->id,
        ]);
    }

    public function test_cancelling_grn_preserves_a_pre_existing_batch(): void
    {
        $this->product->update([
            'inventory_tracking_type' => InventoryTrackingTypeEnum::BATCH,
        ]);

        $purchaseOrderItem = $this->createPurchaseOrderItem(
            status: 'confirmed'
        );

        $existingBatch = ProductBatch::query()->create([
            'product_id' => $this->product->id,
            'product_variant_id' => null,
            'warehouse_id' => $this->warehouse->id,
            'batch_no' => 'EXISTING-BATCH-001',
            'unit_cost' => 400,
            'original_quantity' => 50,
            'remaining_quantity' => 50,
            'status' => ProductBatchStatusEnum::ACTIVE,
        ]);

        $payload = $this->grnPayload($purchaseOrderItem);

        $payload['items'][0]['batches'] = [
            [
                'batch_no' => 'EXISTING-BATCH-001',
                'quantity' => 20,
            ],
        ];

        $grn = $this->service->create($payload);

        $this->postJson(
            '/api/goods-receipt-notes/' . $grn->uuid . '/confirm'
        )
            ->assertOk()
            ->assertJsonPath('data.status', 'confirmed');

        $existingBatch->refresh();

        $this->assertSame(
            ProductBatchStatusEnum::ACTIVE,
            $existingBatch->status
        );

        $this->assertEquals(
            50,
            (float) $existingBatch->original_quantity
        );

        $this->assertEquals(
            50,
            (float) $existingBatch->remaining_quantity,
            'GRN confirmation must not mutate inventory batches before Purchase approval.'
        );

        $this->postJson(
            '/api/goods-receipt-notes/' . $grn->uuid . '/cancel'
        )
            ->assertOk()
            ->assertJsonPath('data.status', 'cancelled');

        $existingBatch->refresh();

        $this->assertSame(
            ProductBatchStatusEnum::ACTIVE,
            $existingBatch->status,
            'A pre-existing batch must remain ACTIVE after GRN cancellation.'
        );

        $this->assertEquals(
            50,
            (float) $existingBatch->original_quantity,
            'Cancellation must preserve the batch original quantity.'
        );

        $this->assertEquals(
            50,
            (float) $existingBatch->remaining_quantity,
            'An unbilled GRN must leave the pre-existing batch unchanged.'
        );
    }

    public function test_cancelling_unbilled_grn_does_not_register_serials(): void
    {
        $this->product->update([
            'inventory_tracking_type' => InventoryTrackingTypeEnum::SERIAL,
        ]);

        $purchaseOrderItem = $this->createPurchaseOrderItem(
            status: 'confirmed'
        );

        $payload = $this->grnPayload($purchaseOrderItem);
        $payload['items'][0]['serials'] = [];

        for ($i = 1; $i <= 20; $i++) {
            $payload['items'][0]['serials'][] = [
                'serial_number' => sprintf('GRN-CANCEL-SERIAL-%03d', $i),
            ];
        }

        $response = $this->postJson(
            '/api/goods-receipt-notes',
            $payload
        );

        $response->assertCreated();
        $uuid = $response->json('data.uuid');

        app(TenantManager::class)->setTenant($this->tenant);

        $grn = GoodsReceiptNote::query()
            ->where('uuid', $uuid)
            ->firstOrFail();

        $this->postJson(
            '/api/goods-receipt-notes/' . $uuid . '/confirm'
        )->assertOk();

        $this->assertDatabaseMissing('product_serials', [
            'serial_number' => 'GRN-CANCEL-SERIAL-001',
            'sourceable_type' => GoodsReceiptNote::class,
            'sourceable_id' => $grn->id,
        ]);

        $this->postJson(
            '/api/goods-receipt-notes/' . $uuid . '/cancel'
        )->assertOk();

        $this->assertDatabaseMissing('product_serials', [
            'sourceable_type' => GoodsReceiptNote::class,
            'sourceable_id' => $grn->id,
        ]);
    }

    public function test_api_cannot_cancel_draft_grn(): void
    {
        $purchaseOrderItem = $this->createPurchaseOrderItem();

        $grn = $this->service->create(
            $this->grnPayload($purchaseOrderItem)
        );

        $this->postJson(
            '/api/goods-receipt-notes/' . $grn->uuid . '/cancel'
        )->assertStatus(422);

        $this->assertDatabaseHas('goods_receipt_notes', [
            'id' => $grn->id,
            'status' => 'draft',
        ]);
    }

    public function test_api_cannot_confirm_grn_twice(): void
    {
        $purchaseOrderItem = $this->createPurchaseOrderItem(
            status: 'confirmed'
        );

        $grn = $this->service->create(
            $this->grnPayload($purchaseOrderItem)
        );

        $this->postJson(
            '/api/goods-receipt-notes/' . $grn->uuid . '/confirm'
        )
            ->assertOk()
            ->assertJsonPath('data.status', 'confirmed');

        $this->postJson(
            '/api/goods-receipt-notes/' . $grn->uuid . '/confirm'
        )->assertStatus(422);

        $this->assertDatabaseHas('goods_receipt_notes', [
            'id' => $grn->id,
            'status' => 'confirmed',
        ]);
    }


    public function test_api_prevents_cross_tenant_access_to_grn(): void
    {
        // Create a GRN belonging to the current tenant.
        $purchaseOrderItem = $this->createPurchaseOrderItem(
            status: 'confirmed'
        );

        $grn = $this->service->create(
            $this->grnPayload($purchaseOrderItem)
        );

        // Create a second tenant and its authenticated user.
        $otherTenant = $this->createTestTenant();

        app(TenantManager::class)
            ->setTenant($otherTenant);

        app(TenantSetupService::class)
            ->setup($otherTenant);

        $otherUser = $this->createTestUser();

        Sanctum::actingAs($otherUser);

        // Ensure requests execute under the second tenant context.
        app(TenantManager::class)
            ->setTenant($otherTenant);

        // A foreign GRN must not be readable.
        // $this->getJson(
        //     '/api/goods-receipt-notes/' . $grn->uuid
        // )->assertNotFound();

        $response = $this->getJson(
            '/api/goods-receipt-notes/' . $grn->uuid
        );

        // $response->dump();

        $response->assertUnprocessable()
            ->assertJsonPath(
                'message',
                'Goods receipt note not found.'
            );

        // A foreign GRN must not be editable.
        $this->putJson(
            '/api/goods-receipt-notes/' . $grn->uuid,
            [
                'remarks' => 'Cross-tenant update attempt',
            ]
        )->assertUnprocessable()
            ->assertJsonPath(
                'message',
                'Goods receipt note not found.'
            );

        // A foreign GRN must not be confirmable.
        $this->postJson(
            '/api/goods-receipt-notes/' . $grn->uuid . '/confirm'
        )->assertUnprocessable()
            ->assertJsonPath(
                'message',
                'Goods receipt note not found.'
            );

        // A foreign GRN must not be cancellable.
        $this->postJson(
            '/api/goods-receipt-notes/' . $grn->uuid . '/cancel'
        )->assertUnprocessable()
            ->assertJsonPath(
                'message',
                'Goods receipt note not found.'
            );

        // Verify the original GRN remains unchanged.
        $this->assertDatabaseHas('goods_receipt_notes', [
            'id' => $grn->id,
            'tenant_id' => $this->tenant->id,
            'status' => 'draft',
        ]);

        $this->assertDatabaseMissing('goods_receipt_notes', [
            'id' => $grn->id,
            'remarks' => 'Cross-tenant update attempt',
        ]);
    }

    public function test_api_rejects_grn_without_items(): void
    {
        $purchaseOrderItem = $this->createPurchaseOrderItem(
            status: 'confirmed'
        );

        $payload = $this->grnPayload($purchaseOrderItem);

        unset($payload['items']);

        $this->postJson(
            '/api/goods-receipt-notes',
            $payload
        )
            ->assertUnprocessable()
            ->assertJsonValidationErrors('items');
    }

    public function test_api_rejects_grn_with_zero_received_quantity(): void
    {
        $purchaseOrderItem = $this->createPurchaseOrderItem(
            status: 'confirmed'
        );

        $payload = $this->grnPayload($purchaseOrderItem);

        $payload['items'][0]['quantity_received'] = 0;

        $this->postJson(
            '/api/goods-receipt-notes',
            $payload
        )
            ->assertUnprocessable()
            ->assertJsonValidationErrors(
                'items.0.quantity_received'
            );
    }

    public function test_api_rejects_grn_with_negative_received_quantity(): void
    {
        $purchaseOrderItem = $this->createPurchaseOrderItem(
            status: 'confirmed'
        );

        $payload = $this->grnPayload($purchaseOrderItem);

        $payload['items'][0]['quantity_received'] = -1;

        $this->postJson(
            '/api/goods-receipt-notes',
            $payload
        )
            ->assertUnprocessable()
            ->assertJsonValidationErrors(
                'items.0.quantity_received'
            );
    }

    public function test_api_rejects_grn_with_invalid_purchase_order(): void
    {
        $purchaseOrderItem = $this->createPurchaseOrderItem(
            status: 'confirmed'
        );

        $payload = $this->grnPayload($purchaseOrderItem);

        $payload['purchase_order_id'] = 999999999;

        $this->postJson(
            '/api/goods-receipt-notes',
            $payload
        )
            ->assertUnprocessable()
            ->assertJsonValidationErrors('purchase_order_id');
    }

    public function test_api_rejects_grn_with_invalid_warehouse(): void
    {
        $purchaseOrderItem = $this->createPurchaseOrderItem(
            status: 'confirmed'
        );

        $payload = $this->grnPayload($purchaseOrderItem);

        $payload['warehouse_id'] = 999999999;

        $this->postJson(
            '/api/goods-receipt-notes',
            $payload
        )
            ->assertUnprocessable()
            ->assertJsonValidationErrors('warehouse_id');
    }

    public function test_api_rejects_grn_with_invalid_product(): void
    {
        $purchaseOrderItem = $this->createPurchaseOrderItem(
            status: 'confirmed'
        );

        $payload = $this->grnPayload($purchaseOrderItem);

        $payload['items'][0]['product_id'] = 999999999;

        $this->postJson(
            '/api/goods-receipt-notes',
            $payload
        )
            ->assertUnprocessable()
            ->assertJsonValidationErrors(
                'items.0.product_id'
            );
    }

    public function test_api_rejects_grn_with_negative_unit_cost(): void
    {
        $purchaseOrderItem = $this->createPurchaseOrderItem(
            status: 'confirmed'
        );

        $payload = $this->grnPayload($purchaseOrderItem);

        $payload['items'][0]['unit_cost'] = -100;

        $this->postJson(
            '/api/goods-receipt-notes',
            $payload
        )
            ->assertUnprocessable()
            ->assertJsonValidationErrors(
                'items.0.unit_cost'
            );
    }

    public function test_api_rejects_grn_with_invalid_unit(): void
    {
        $purchaseOrderItem = $this->createPurchaseOrderItem(
            status: 'confirmed'
        );

        $payload = $this->grnPayload($purchaseOrderItem);

        $payload['items'][0]['unit_id'] = 999999999;

        $this->postJson(
            '/api/goods-receipt-notes',
            $payload
        )
            ->assertUnprocessable()
            ->assertJsonValidationErrors(
                'items.0.unit_id'
            );
    }

    public function test_api_rejects_grn_item_without_received_quantity(): void
    {
        $purchaseOrderItem = $this->createPurchaseOrderItem(
            status: 'confirmed'
        );

        $payload = $this->grnPayload($purchaseOrderItem);

        unset($payload['items'][0]['quantity_received']);

        $this->postJson(
            '/api/goods-receipt-notes',
            $payload
        )
            ->assertUnprocessable()
            ->assertJsonValidationErrors(
                'items.0.quantity_received'
            );
    }

    public function test_api_rejects_grn_with_empty_item_row(): void
    {
        $purchaseOrderItem = $this->createPurchaseOrderItem(
            status: 'confirmed'
        );

        $payload = $this->grnPayload($purchaseOrderItem);

        $payload['items'] = [[]];

        $this->postJson(
            '/api/goods-receipt-notes',
            $payload
        )
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'items.0.product_id',
                'items.0.unit_id',
                'items.0.quantity_received',
                'items.0.unit_cost',
            ]);
    }

    public function test_api_rejects_quantity_exceeding_purchase_order_remaining_quantity(): void
    {
        $purchaseOrderItem = $this->createPurchaseOrderItem(
            status: 'confirmed'
        );

        $payload = $this->grnPayload($purchaseOrderItem);

        $payload['items'][0]['quantity_received'] = 11;

        // Draft creation is allowed; receiving limits are checked on confirmation.
        $response = $this->postJson(
            '/api/goods-receipt-notes',
            $payload
        );

        $response->assertCreated();

        $uuid = $response->json('data.uuid');

        $this->postJson(
            '/api/goods-receipt-notes/' . $uuid . '/confirm'
        )
            ->assertUnprocessable();

        // The failed confirmation must not update received quantity.
        $this->assertDatabaseHas('purchase_order_items', [
            'id' => $purchaseOrderItem->id,
            'received_quantity' => 0,
        ]);
    }

    public function test_api_rejects_confirmation_for_draft_purchase_order(): void
    {
        $purchaseOrderItem = $this->createPurchaseOrderItem(
            status: 'draft'
        );

        $payload = $this->grnPayload($purchaseOrderItem);

        $response = $this->postJson(
            '/api/goods-receipt-notes',
            $payload
        );

        $response->assertCreated();

        $uuid = $response->json('data.uuid');

        $this->postJson(
            '/api/goods-receipt-notes/' . $uuid . '/confirm'
        )
            ->assertUnprocessable();

        $this->assertDatabaseHas('purchase_order_items', [
            'id' => $purchaseOrderItem->id,
            'received_quantity' => 0,
        ]);
    }

    public function test_api_rejects_confirmation_for_cancelled_purchase_order(): void
    {
        $purchaseOrderItem = $this->createPurchaseOrderItem(
            status: 'cancelled'
        );

        $payload = $this->grnPayload($purchaseOrderItem);

        $response = $this->postJson(
            '/api/goods-receipt-notes',
            $payload
        );

        $response->assertCreated();

        $uuid = $response->json('data.uuid');

        $this->postJson(
            '/api/goods-receipt-notes/' . $uuid . '/confirm'
        )
            ->assertUnprocessable();

        $this->assertDatabaseHas('purchase_order_items', [
            'id' => $purchaseOrderItem->id,
            'received_quantity' => 0,
        ]);
    }

    public function test_api_reconfirming_grn_does_not_duplicate_received_quantity_or_post_stock(): void
    {
        $purchaseOrderItem = $this->createPurchaseOrderItem(
            status: 'confirmed'
        );

        $response = $this->postJson(
            '/api/goods-receipt-notes',
            $this->grnPayload($purchaseOrderItem)
        );

        $response->assertCreated();
        $uuid = $response->json('data.uuid');

        $this->postJson(
            '/api/goods-receipt-notes/' . $uuid . '/confirm'
        )->assertOk();

        $this->postJson(
            '/api/goods-receipt-notes/' . $uuid . '/confirm'
        )->assertUnprocessable();

        app(TenantManager::class)->setTenant($this->tenant);

        $grn = GoodsReceiptNote::query()->where('uuid', $uuid)->firstOrFail();

        $this->assertDatabaseHas('purchase_order_items', [
            'id' => $purchaseOrderItem->id,
            'received_quantity' => 2,
        ]);

        $this->assertDatabaseMissing('product_stocks', [
            'product_id' => $purchaseOrderItem->product_id,
            'warehouse_id' => $purchaseOrderItem->purchaseOrder->warehouse_id,
        ]);

        $this->assertDatabaseMissing('stock_ledgers', [
            'referenceable_type' => GoodsReceiptNote::class,
            'referenceable_id' => $grn->id,
        ]);
    }

    public function test_api_recancelling_unbilled_grn_does_not_touch_inventory(): void
    {
        $purchaseOrderItem = $this->createPurchaseOrderItem(
            status: 'confirmed'
        );

        $response = $this->postJson(
            '/api/goods-receipt-notes',
            $this->grnPayload($purchaseOrderItem)
        );

        $response->assertCreated();
        $uuid = $response->json('data.uuid');

        $this->postJson(
            '/api/goods-receipt-notes/' . $uuid . '/confirm'
        )->assertOk();

        $this->postJson(
            '/api/goods-receipt-notes/' . $uuid . '/cancel'
        )->assertOk();

        $this->postJson(
            '/api/goods-receipt-notes/' . $uuid . '/cancel'
        )->assertUnprocessable();

        app(TenantManager::class)->setTenant($this->tenant);

        $grn = GoodsReceiptNote::query()->where('uuid', $uuid)->firstOrFail();

        $this->assertDatabaseHas('purchase_order_items', [
            'id' => $purchaseOrderItem->id,
            'received_quantity' => 0,
        ]);

        $this->assertDatabaseMissing('product_stocks', [
            'product_id' => $purchaseOrderItem->product_id,
            'warehouse_id' => $purchaseOrderItem->purchaseOrder->warehouse_id,
        ]);

        $this->assertDatabaseMissing('stock_ledgers', [
            'referenceable_type' => GoodsReceiptNote::class,
            'referenceable_id' => $grn->id,
        ]);
    }

    public function test_api_failed_grn_confirmation_preserves_draft_and_inventory(): void
    {
        $purchaseOrderItem = $this->createPurchaseOrderItem(
            status: 'confirmed'
        );

        $payload = $this->grnPayload($purchaseOrderItem);

        $payload['items'][0]['quantity_received'] = 11;

        $response = $this->postJson(
            '/api/goods-receipt-notes',
            $payload
        );

        $response->assertCreated();

        $uuid = $response->json('data.uuid');

        $this->postJson(
            '/api/goods-receipt-notes/' . $uuid . '/confirm'
        )->assertUnprocessable();

        $this->assertDatabaseHas('goods_receipt_notes', [
            'uuid' => $uuid,
            'status' => 'draft',
        ]);

        $this->assertDatabaseHas('purchase_order_items', [
            'id' => $purchaseOrderItem->id,
            'received_quantity' => 0,
        ]);

        $this->assertDatabaseMissing('product_stocks', [
            'product_id' => $purchaseOrderItem->product_id,
            'warehouse_id' => $purchaseOrderItem->purchaseOrder->warehouse_id,
        ]);
    }

    public function test_api_rejects_updating_confirmed_grn(): void
    {
        $purchaseOrderItem = $this->createPurchaseOrderItem(
            status: 'confirmed'
        );

        $response = $this->postJson(
            '/api/goods-receipt-notes',
            $this->grnPayload($purchaseOrderItem)
        );

        $response->assertCreated();

        $uuid = $response->json('data.uuid');

        $this->postJson(
            '/api/goods-receipt-notes/' . $uuid . '/confirm'
        )->assertOk();

        $this->putJson(
            '/api/goods-receipt-notes/' . $uuid,
            [
                'remarks' => 'Attempt to edit confirmed GRN',
            ]
        )->assertUnprocessable();

        $this->assertDatabaseHas('goods_receipt_notes', [
            'uuid' => $uuid,
            'status' => 'confirmed',
        ]);
    }

    public function test_api_rejects_updating_cancelled_grn(): void
    {
        $purchaseOrderItem = $this->createPurchaseOrderItem(
            status: 'confirmed'
        );

        $response = $this->postJson(
            '/api/goods-receipt-notes',
            $this->grnPayload($purchaseOrderItem)
        );

        $response->assertCreated();

        $uuid = $response->json('data.uuid');

        $this->postJson(
            '/api/goods-receipt-notes/' . $uuid . '/confirm'
        )->assertOk();

        $this->postJson(
            '/api/goods-receipt-notes/' . $uuid . '/cancel'
        )->assertOk();

        $this->putJson(
            '/api/goods-receipt-notes/' . $uuid,
            [
                'remarks' => 'Attempt to edit cancelled GRN',
            ]
        )->assertUnprocessable();

        $this->assertDatabaseHas('goods_receipt_notes', [
            'uuid' => $uuid,
            'status' => 'cancelled',
        ]);
    }

    public function test_api_cancelling_grn_restores_purchase_order_receiving_status(): void
    {
        $purchaseOrderItem = $this->createPurchaseOrderItem(
            status: 'confirmed'
        );

        $response = $this->postJson(
            '/api/goods-receipt-notes',
            $this->grnPayload($purchaseOrderItem)
        );

        $response->assertCreated();

        $uuid = $response->json('data.uuid');

        $this->postJson(
            '/api/goods-receipt-notes/' . $uuid . '/confirm'
        )->assertOk();

        $this->assertDatabaseHas('purchase_orders', [
            'id' => $purchaseOrderItem->purchase_order_id,
            'status' => 'partially_received',
        ]);

        $this->postJson(
            '/api/goods-receipt-notes/' . $uuid . '/cancel'
        )->assertOk();

        $this->assertDatabaseHas('purchase_orders', [
            'id' => $purchaseOrderItem->purchase_order_id,
            'status' => 'confirmed',
        ]);

        $this->assertDatabaseHas('purchase_order_items', [
            'id' => $purchaseOrderItem->id,
            'received_quantity' => 0,
        ]);
    }

    public function test_api_receiving_full_purchase_order_quantity_updates_status(): void
    {
        $purchaseOrderItem = $this->createPurchaseOrderItem(
            status: 'confirmed'
        );

        $payload = $this->grnPayload($purchaseOrderItem);

        // The PO has 10 purchase units ordered and none received.
        $payload['items'][0]['quantity_received'] = 10;

        $response = $this->postJson(
            '/api/goods-receipt-notes',
            $payload
        );

        $response->assertCreated();

        $uuid = $response->json('data.uuid');

        $this->postJson(
            '/api/goods-receipt-notes/' . $uuid . '/confirm'
        )->assertOk();

        $this->assertDatabaseHas('purchase_order_items', [
            'id' => $purchaseOrderItem->id,
            'received_quantity' => 10,
        ]);

        $this->assertDatabaseHas('purchase_orders', [
            'id' => $purchaseOrderItem->purchase_order_id,
            'status' => 'fully_received',
        ]);
    }


    public function test_api_updates_draft_grn_item_and_recalculates_valuation(): void
    {
        $purchaseOrderItem = $this->createPurchaseOrderItem();

        $grn = $this->service->create(
            $this->grnPayload($purchaseOrderItem)
        );

        $item = $grn->items()->firstOrFail();

        $this->putJson(
            '/api/goods-receipt-notes/'
                . $grn->uuid
                . '/items/'
                . $item->uuid,
            [
                'quantity_received' => 3,
                'unit_cost' => 600,
            ]
        )
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.uuid', $item->uuid)
            ->assertJsonPath('data.quantity_received', '3.0000')
            ->assertJsonPath('data.unit_cost', '600.0000')
            ->assertJsonPath('data.line_total', '1800.0000');

        $this->assertDatabaseHas('goods_receipt_note_items', [
            'id' => $item->id,
            'quantity_received' => 3,
            'base_quantity' => 30,
            'unit_cost' => 600,
            'line_total' => 1800,
        ]);

        $this->assertDatabaseHas('goods_receipt_notes', [
            'id' => $grn->id,
            'total_received_valuation' => 1800,
        ]);
    }

    public function test_api_rejects_item_from_another_grn(): void
    {
        $firstPoItem = $this->createPurchaseOrderItem();
        $secondPoItem = $this->createPurchaseOrderItem();

        $firstGrn = $this->service->create(
            $this->grnPayload($firstPoItem)
        );

        $secondGrn = $this->service->create(
            $this->grnPayload($secondPoItem)
        );

        $firstItem = $firstGrn->items()->firstOrFail();
        $secondItem = $secondGrn->items()->firstOrFail();

        $this->putJson(
            '/api/goods-receipt-notes/'
                . $firstGrn->uuid
                . '/items/'
                . $secondItem->uuid,
            ['remarks' => 'Must not update another GRN']
        )->assertNotFound();

        $this->deleteJson(
            '/api/goods-receipt-notes/'
                . $firstGrn->uuid
                . '/items/'
                . $secondItem->uuid
        )->assertNotFound();

        $this->assertDatabaseHas('goods_receipt_note_items', [
            'id' => $firstItem->id,
            'goods_receipt_note_id' => $firstGrn->id,
        ]);

        $this->assertDatabaseHas('goods_receipt_note_items', [
            'id' => $secondItem->id,
            'goods_receipt_note_id' => $secondGrn->id,
        ]);
    }

    public function test_api_deletes_draft_grn_item_and_resets_valuation(): void
    {
        $purchaseOrderItem = $this->createPurchaseOrderItem();

        $grn = $this->service->create(
            $this->grnPayload($purchaseOrderItem)
        );

        $item = $grn->items()->firstOrFail();

        $this->deleteJson(
            '/api/goods-receipt-notes/'
                . $grn->uuid
                . '/items/'
                . $item->uuid
        )
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath(
                'message',
                'Goods receipt note item deleted successfully.'
            );

        $this->assertDatabaseMissing('goods_receipt_note_items', [
            'id' => $item->id,
        ]);

        $this->assertDatabaseHas('goods_receipt_notes', [
            'id' => $grn->id,
            'total_received_valuation' => 0,
        ]);
    }

    public function test_api_rejects_item_changes_after_grn_confirmation(): void
    {
        $purchaseOrderItem = $this->createPurchaseOrderItem(
            status: 'confirmed'
        );

        $grn = $this->service->create(
            $this->grnPayload($purchaseOrderItem)
        );

        $item = $grn->items()->firstOrFail();

        $this->postJson(
            '/api/goods-receipt-notes/'
                . $grn->uuid
                . '/confirm'
        )->assertOk();

        $this->putJson(
            '/api/goods-receipt-notes/'
                . $grn->uuid
                . '/items/'
                . $item->uuid,
            ['quantity_received' => 3]
        )->assertUnprocessable();

        $this->deleteJson(
            '/api/goods-receipt-notes/'
                . $grn->uuid
                . '/items/'
                . $item->uuid
        )->assertUnprocessable();

        $this->assertDatabaseHas('goods_receipt_note_items', [
            'id' => $item->id,
            'quantity_received' => 2,
        ]);

        $this->assertDatabaseHas('goods_receipt_notes', [
            'id' => $grn->id,
            'status' => 'confirmed',
        ]);
    }

    protected function createPurchaseOrderItem(
        string $status = 'draft'
    ): PurchaseOrderItem {
        $purchaseOrder = PurchaseOrder::create([
            'tenant_id' => $this->tenant->id,
            'supplier_id' => $this->supplier->id,
            'warehouse_id' => $this->warehouse->id,
            'order_number' => 'PO-API-' . uniqid(),
            'order_date' => now()->toDateString(),
            'status' => $status,
            'currency_code' => 'INR',
            'exchange_rate' => 1,
            'subtotal_amount' => 5000,
            'discount_amount' => 0,
            'additional_charge_amount' => 0,
            'tax_total_amount' => 0,
            'rounding_adjustment' => 0,
            'grand_total_amount' => 5000,
        ]);

        $item = PurchaseOrderItem::create([
            'tenant_id' => $this->tenant->id,
            'purchase_order_id' => $purchaseOrder->id,
            'product_id' => $this->product->id,
            'product_variant_id' => null,
            'warehouse_id' => $this->warehouse->id,
            'line_type' => 'product',
            'item_name' => $this->product->name,
            'unit_of_measure_id' => $this->purchaseUnit->id,
            'quantity' => 10,
            'received_quantity' => 0,
            'billed_quantity' => 0,
            'cancelled_quantity' => 0,
            'unit_price' => 500,
            'discount_type' => 'fixed',
            'discount_rate' => 0,
            'discount_amount' => 0,
            'net_unit_price' => 500,
            'subtotal_amount' => 5000,
            'tax_group_id' => null,
            'is_tax_inclusive' => false,
            'tax_base_amount' => 5000,
            'tax_total_amount' => 0,
            'total_amount' => 5000,
        ]);

        return $item->load('purchaseOrder');
    }

    protected function grnPayload(
        PurchaseOrderItem $purchaseOrderItem
    ): array {
        return [
            'grn_date' => now()->toDateString(),
            'purchase_order_id' =>
            $purchaseOrderItem->purchase_order_id,
            'warehouse_id' => $this->warehouse->id,
            'delivery_challan_number' => 'DC-API-TEST',
            'delivery_challan_date' => now()->toDateString(),
            'vehicle_number' => 'WB-01-TEST',
            'transporter_name' => 'API Test Transporter',
            'remarks' => 'Created by API feature test',
            'items' => [
                [
                    'purchase_order_item_id' =>
                    $purchaseOrderItem->id,
                    'product_id' =>
                    $purchaseOrderItem->product_id,
                    'unit_id' =>
                    $purchaseOrderItem->unit_of_measure_id,
                    'quantity_received' => 2,
                    'unit_cost' => 500,
                    'tax_rate' => 0,
                    'tax_amount' => 0,
                ],
            ],
        ];
    }
}
