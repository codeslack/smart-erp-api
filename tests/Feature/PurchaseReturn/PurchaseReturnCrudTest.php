<?php

namespace Tests\Feature\PurchaseReturn;

use App\Modules\Accounting\Services\AccountingSetupService;
use App\Modules\Product\Enums\InventoryTrackingTypeEnum;
use App\Modules\Product\Enums\ProductTypeEnum;
use App\Modules\Product\Models\Product;
use App\Modules\Purchase\Models\Purchase;
use App\Modules\Purchase\Services\PurchaseService;
use App\Modules\PurchaseReturn\Enums\PurchaseReturnStatus;
use App\Modules\PurchaseReturn\Models\PurchaseReturn;
use App\Modules\Supplier\Models\Supplier;
use App\Modules\Tenant\Models\Tenant;
use App\Modules\User\Models\User;
use App\Modules\Warehouse\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Support\CreatesTenant;
use Tests\Support\CreatesUser;
use Tests\TestCase;

class PurchaseReturnCrudTest extends TestCase
{
    use RefreshDatabase;
    use CreatesTenant;
    use CreatesUser;

    protected Tenant $tenant;
    protected User $user;
    protected Supplier $supplier;
    protected Warehouse $warehouse;
    protected Product $product;
    protected Purchase $purchase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = $this->createTestTenant();
        $this->user = $this->createTestUser();
        Sanctum::actingAs($this->user);

        app(AccountingSetupService::class)->setup($this->tenant);

        $this->supplier = Supplier::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Acme Supplier',
            'is_active' => true,
        ]);

        $this->warehouse = Warehouse::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Main Warehouse',
            'code' => 'WH-MAIN',
            'is_active' => true,
        ]);

        $this->product = Product::create([
            'tenant_id' => $this->tenant->id,
            'code' => 'PRD-RET-01',
            'sku' => 'SKU-RET-01',
            'name' => 'Returnable Widget',
            'slug' => 'returnable-widget',
            'product_type' => ProductTypeEnum::PRODUCT,
            'inventory_tracking_type' => InventoryTrackingTypeEnum::NONE,
            'is_active' => true,
            'purchase_price' => 100,
            'selling_price' => 150,
        ]);

        // Create and confirm a purchase
        $purchaseService = app(PurchaseService::class);
        $this->purchase = $purchaseService->create([
            'supplier_uuid' => $this->supplier->uuid,
            'warehouse_uuid' => $this->warehouse->uuid,
            'purchase_date' => now()->toDateString(),
            'items' => [
                [
                    'product_uuid' => $this->product->uuid,
                    'quantity' => 10,
                    'unit_cost' => 100,
                ],
            ],
        ]);
        $this->purchase = $purchaseService->confirm($this->purchase);
    }

    public function test_can_create_draft_purchase_return(): void
    {
        $purchaseItem = $this->purchase->items->first();

        $payload = [
            'purchase_uuid' => $this->purchase->uuid,
            'return_date' => now()->toDateString(),
            'return_reason' => 'Damaged during delivery',
            'items' => [
                [
                    'purchase_item_uuid' => $purchaseItem->uuid,
                    'product_uuid' => $this->product->uuid,
                    'warehouse_uuid' => $this->warehouse->uuid,
                    'returned_quantity' => 2,
                    'unit_cost' => 100,
                ],
            ],
        ];

        $response = $this->postJson('/api/purchase-returns', $payload);

        $response->assertStatus(201);
        $response->assertJsonPath('success', true);
        $response->assertJsonPath('data.status', 'draft');
        $response->assertJsonPath('data.subtotal', 200);
        $response->assertJsonPath('data.grand_total', 200);
        $this->assertArrayNotHasKey('id', $response->json('data'));
        $this->assertArrayHasKey('uuid', $response->json('data'));

        $this->assertDatabaseHas('purchase_returns', [
            'purchase_id' => $this->purchase->id,
            'status' => 'draft',
            'subtotal' => 200,
            'grand_total' => 200,
        ]);
    }

    public function test_can_read_purchase_return_by_uuid(): void
    {
        $purchaseItem = $this->purchase->items->first();

        $response = $this->postJson('/api/purchase-returns', [
            'purchase_uuid' => $this->purchase->uuid,
            'return_date' => now()->toDateString(),
            'items' => [
                [
                    'purchase_item_uuid' => $purchaseItem->uuid,
                    'returned_quantity' => 3,
                    'unit_cost' => 100,
                ],
            ],
        ]);

        $uuid = $response->json('data.uuid');

        $showResponse = $this->getJson("/api/purchase-returns/{$uuid}");
        $showResponse->assertStatus(200);
        $showResponse->assertJsonPath('data.uuid', $uuid);
        $showResponse->assertJsonPath('data.grand_total', 300);
        $this->assertArrayNotHasKey('id', $showResponse->json('data'));
        $this->assertArrayNotHasKey('id', $showResponse->json('data.items.0'));
        $this->assertArrayHasKey('uuid', $showResponse->json('data.items.0'));
    }

    public function test_can_update_draft_purchase_return(): void
    {
        $purchaseItem = $this->purchase->items->first();

        $createResponse = $this->postJson('/api/purchase-returns', [
            'purchase_uuid' => $this->purchase->uuid,
            'return_date' => now()->toDateString(),
            'items' => [
                [
                    'purchase_item_uuid' => $purchaseItem->uuid,
                    'returned_quantity' => 1,
                    'unit_cost' => 100,
                ],
            ],
        ]);

        $uuid = $createResponse->json('data.uuid');

        $updateResponse = $this->putJson("/api/purchase-returns/{$uuid}", [
            'return_reason' => 'Updated defect reason',
            'items' => [
                [
                    'purchase_item_uuid' => $purchaseItem->uuid,
                    'returned_quantity' => 4,
                    'unit_cost' => 999,
                ],
            ],
        ]);

        $updateResponse->assertStatus(200);
        $updateResponse->assertJsonPath('data.grand_total', 400);
        $updateResponse->assertJsonPath('data.return_reason', 'Updated defect reason');
    }

    public function test_can_delete_draft_purchase_return(): void
    {
        $purchaseItem = $this->purchase->items->first();

        $createResponse = $this->postJson('/api/purchase-returns', [
            'purchase_uuid' => $this->purchase->uuid,
            'return_date' => now()->toDateString(),
            'items' => [
                [
                    'purchase_item_uuid' => $purchaseItem->uuid,
                    'returned_quantity' => 1,
                    'unit_cost' => 100,
                ],
            ],
        ]);

        $uuid = $createResponse->json('data.uuid');

        $deleteResponse = $this->deleteJson("/api/purchase-returns/{$uuid}");
        $deleteResponse->assertStatus(200);

        $this->assertSoftDeleted('purchase_returns', [
            'uuid' => $uuid,
        ]);
    }
}
