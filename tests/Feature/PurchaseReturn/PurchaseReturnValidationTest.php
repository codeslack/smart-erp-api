<?php

namespace Tests\Feature\PurchaseReturn;

use App\Modules\Accounting\Services\AccountingSetupService;
use App\Modules\Product\Enums\InventoryTrackingTypeEnum;
use App\Modules\Product\Enums\ProductTypeEnum;
use App\Modules\Product\Models\Product;
use App\Modules\Product\Models\ProductVariant;
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

class PurchaseReturnValidationTest extends TestCase
{
    use RefreshDatabase;
    use CreatesTenant;
    use CreatesUser;

    protected Tenant $tenant;
    protected User $user;
    protected Supplier $supplier;
    protected Warehouse $warehouse;
    protected Product $product;
    protected Product $otherProduct;
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
            'name' => 'Validation Supplier',
            'is_active' => true,
        ]);

        $this->warehouse = Warehouse::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Validation Warehouse',
            'code' => 'WH-VAL',
            'is_active' => true,
        ]);

        $this->product = Product::create([
            'tenant_id' => $this->tenant->id,
            'code' => 'PRD-VAL-01',
            'sku' => 'SKU-VAL-01',
            'name' => 'Validation Widget',
            'slug' => 'validation-widget',
            'product_type' => ProductTypeEnum::PRODUCT,
            'inventory_tracking_type' => InventoryTrackingTypeEnum::NONE,
            'is_active' => true,
            'purchase_price' => 50,
            'selling_price' => 80,
        ]);

        $this->otherProduct = Product::create([
            'tenant_id' => $this->tenant->id,
            'code' => 'PRD-VAL-02',
            'sku' => 'SKU-VAL-02',
            'name' => 'Other Widget',
            'slug' => 'other-widget',
            'product_type' => ProductTypeEnum::PRODUCT,
            'inventory_tracking_type' => InventoryTrackingTypeEnum::NONE,
            'is_active' => true,
            'purchase_price' => 70,
            'selling_price' => 100,
        ]);

        $purchaseService = app(PurchaseService::class);
        $this->purchase = $purchaseService->create([
            'supplier_uuid' => $this->supplier->uuid,
            'warehouse_uuid' => $this->warehouse->uuid,
            'purchase_date' => now()->toDateString(),
            'items' => [
                [
                    'product_uuid' => $this->product->uuid,
                    'quantity' => 10,
                    'unit_cost' => 50,
                ],
            ],
        ]);
        $this->purchase = $purchaseService->confirm($this->purchase);
    }

    public function test_cannot_return_more_than_available_quantity(): void
    {
        $purchaseItem = $this->purchase->items->first();

        $response = $this->postJson('/api/purchase-returns', [
            'purchase_uuid' => $this->purchase->uuid,
            'return_date' => now()->toDateString(),
            'items' => [
                [
                    'purchase_item_uuid' => $purchaseItem->uuid,
                    'returned_quantity' => 11, // only 10 purchased
                    'unit_cost' => 50,
                ],
            ],
        ]);

        $response->assertStatus(422);
    }

    public function test_multiple_partial_returns_cannot_exceed_total_purchased_quantity(): void
    {
        $purchaseItem = $this->purchase->items->first();

        // First return 6 items
        $res1 = $this->postJson('/api/purchase-returns', [
            'purchase_uuid' => $this->purchase->uuid,
            'return_date' => now()->toDateString(),
            'items' => [
                [
                    'purchase_item_uuid' => $purchaseItem->uuid,
                    'returned_quantity' => 6,
                    'unit_cost' => 50,
                ],
            ],
        ]);
        $res1->assertStatus(201);
        $uuid1 = $res1->json('data.uuid');

        // Confirm first return
        $confirmRes1 = $this->postJson("/api/purchase-returns/{$uuid1}/confirm");
        $confirmRes1->assertStatus(200);

        // Second return trying to return 5 items (only 4 available)
        $res2 = $this->postJson('/api/purchase-returns', [
            'purchase_uuid' => $this->purchase->uuid,
            'return_date' => now()->toDateString(),
            'items' => [
                [
                    'purchase_item_uuid' => $purchaseItem->uuid,
                    'returned_quantity' => 5,
                    'unit_cost' => 50,
                ],
            ],
        ]);
        $res2->assertStatus(422);

        // Second return of 4 items succeeds
        $res3 = $this->postJson('/api/purchase-returns', [
            'purchase_uuid' => $this->purchase->uuid,
            'return_date' => now()->toDateString(),
            'items' => [
                [
                    'purchase_item_uuid' => $purchaseItem->uuid,
                    'returned_quantity' => 4,
                    'unit_cost' => 50,
                ],
            ],
        ]);
        $res3->assertStatus(201);
    }

    public function test_uses_original_purchase_cost_instead_of_client_supplied_cost(): void
    {
        $purchaseItem = $this->purchase->items->first();

        $response = $this->postJson('/api/purchase-returns', [
            'purchase_uuid' => $this->purchase->uuid,
            'return_date' => now()->toDateString(),
            'items' => [
                [
                    'purchase_item_uuid' => $purchaseItem->uuid,
                    'returned_quantity' => 2,
                    'unit_cost' => 999,
                ],
            ],
        ]);

        $response
            ->assertStatus(201)
            ->assertJsonPath('data.grand_total', 100);

        $this->assertDatabaseHas('purchase_return_items', [
            'purchase_item_id' => $purchaseItem->id,
            'returned_quantity' => 2,
            'unit_cost' => 50,
            'base_quantity' => 2,
        ]);
    }

    public function test_uses_original_purchase_discount_and_tax_values(): void
    {
        $purchaseItem = $this->purchase->items->first();

        $response = $this->postJson('/api/purchase-returns', [
            'purchase_uuid' => $this->purchase->uuid,
            'return_date' => now()->toDateString(),
            'items' => [[
                'purchase_item_uuid' => $purchaseItem->uuid,
                'returned_quantity' => 2,
                'unit_cost' => 50,
                'discount_amount' => 500,
                'tax_amount' => 500,
                'tax_rate' => 99,
            ]],
        ]);

        $response
            ->assertCreated()
            ->assertJsonPath('data.grand_total', 100);

        $this->assertDatabaseHas('purchase_return_items', [
            'purchase_item_id' => $purchaseItem->id,
            'returned_quantity' => 2,
            'unit_cost' => 50,
            'discount_amount' => 0,
            'tax_amount' => 0,
            'tax_rate' => 0,
        ]);
    }

    public function test_rejects_forged_batch_and_serial_ids_on_return_create(): void
    {
        $purchaseItem = $this->purchase->items->first();

        $response = $this->postJson('/api/purchase-returns', [
            'purchase_uuid' => $this->purchase->uuid,
            'return_date' => now()->toDateString(),
            'items' => [[
                'purchase_item_uuid' => $purchaseItem->uuid,
                'returned_quantity' => 1,
                'unit_cost' => 50,
                'batches' => [[
                    'batch_no' => 'CLIENT-BATCH',
                    'quantity' => 1,
                    'product_batch_id' => 999,
                ]],
                'serials' => [[
                    'serial_number' => 'CLIENT-SERIAL',
                    'product_serial_id' => 999,
                ]],
            ]],
        ]);

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'items.0.batches.0',
                'items.0.serials.0',
            ]);
    }

    public function test_rejects_forged_batch_and_serial_ids_on_return_update(): void
    {
        $purchaseItem = $this->purchase->items->first();

        $createResponse = $this->postJson('/api/purchase-returns', [
            'purchase_uuid' => $this->purchase->uuid,
            'return_date' => now()->toDateString(),
            'items' => [[
                'purchase_item_uuid' => $purchaseItem->uuid,
                'returned_quantity' => 1,
                'unit_cost' => 50,
            ]],
        ]);

        $createResponse->assertCreated();
        $uuid = $createResponse->json('data.uuid');

        $response = $this->putJson("/api/purchase-returns/{$uuid}", [
            'items' => [[
                'purchase_item_uuid' => $purchaseItem->uuid,
                'returned_quantity' => 1,
                'unit_cost' => 50,
                'batches' => [[
                    'batch_no' => 'CLIENT-BATCH',
                    'quantity' => 1,
                    'product_batch_id' => 999,
                ]],
                'serials' => [[
                    'serial_number' => 'CLIENT-SERIAL',
                    'product_serial_id' => 999,
                ]],
            ]],
        ]);

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'items.0.batches.0',
                'items.0.serials.0',
            ]);
    }

    public function test_rejects_unvalidated_base_quantity_on_return_items(): void
    {
        $purchaseItem = $this->purchase->items->first();

        $response = $this->postJson('/api/purchase-returns', [
            'purchase_uuid' => $this->purchase->uuid,
            'return_date' => now()->toDateString(),
            'items' => [
                [
                    'purchase_item_uuid' => $purchaseItem->uuid,
                    'returned_quantity' => 2,
                    'unit_cost' => 50,
                    'base_quantity' => 500,
                ],
            ],
        ]);

        $response
            ->assertStatus(422)
            ->assertJsonValidationErrors('items.0');
    }

    public function test_rejects_unvalidated_base_quantity_on_return_item_update(): void
    {
        $purchaseItem = $this->purchase->items->first();

        $createResponse = $this->postJson('/api/purchase-returns', [
            'purchase_uuid' => $this->purchase->uuid,
            'return_date' => now()->toDateString(),
            'items' => [
                [
                    'purchase_item_uuid' => $purchaseItem->uuid,
                    'returned_quantity' => 1,
                    'unit_cost' => 50,
                ],
            ],
        ]);

        $createResponse->assertStatus(201);
        $uuid = $createResponse->json('data.uuid');

        $response = $this->putJson("/api/purchase-returns/{$uuid}", [
            'items' => [
                [
                    'purchase_item_uuid' => $purchaseItem->uuid,
                    'returned_quantity' => 2,
                    'unit_cost' => 50,
                    'base_quantity' => 500,
                ],
            ],
        ]);

        $response
            ->assertStatus(422)
            ->assertJsonValidationErrors('items.0');
    }

    public function test_cannot_return_against_unconfirmed_purchase(): void
    {
        $purchaseService = app(PurchaseService::class);
        $draftPurchase = $purchaseService->create([
            'supplier_uuid' => $this->supplier->uuid,
            'warehouse_uuid' => $this->warehouse->uuid,
            'purchase_date' => now()->toDateString(),
            'items' => [
                [
                    'product_uuid' => $this->product->uuid,
                    'quantity' => 5,
                    'unit_cost' => 50,
                ],
            ],
        ]);

        $item = $draftPurchase->items->first();

        $response = $this->postJson('/api/purchase-returns', [
            'purchase_uuid' => $draftPurchase->uuid,
            'return_date' => now()->toDateString(),
            'items' => [
                [
                    'purchase_item_uuid' => $item->uuid,
                    'returned_quantity' => 1,
                    'unit_cost' => 50,
                ],
            ],
        ]);

        $response->assertStatus(422);
    }

    public function test_cannot_return_with_mismatched_product(): void
    {
        $purchaseItem = $this->purchase->items->first();

        $response = $this->postJson('/api/purchase-returns', [
            'purchase_uuid' => $this->purchase->uuid,
            'return_date' => now()->toDateString(),
            'items' => [
                [
                    'purchase_item_uuid' => $purchaseItem->uuid,
                    'product_uuid' => $this->otherProduct->uuid, // mismatch!
                    'returned_quantity' => 2,
                    'unit_cost' => 50,
                ],
            ],
        ]);

        $response->assertStatus(422);
    }
}
