<?php

namespace Tests\Feature\Purchase;

use App\Modules\Tenant\Models\Tenant;
use App\Modules\User\Models\User;
use App\Modules\Supplier\Models\Supplier;
use App\Modules\Warehouse\Models\Warehouse;
use App\Modules\Product\Models\Product;
use App\Modules\Product\Enums\ProductTypeEnum;
use App\Modules\Product\Enums\InventoryTrackingTypeEnum;
use App\Modules\Inventory\Models\ProductSerial;
use App\Modules\Inventory\Enums\ProductSerialStatusEnum;
use App\Modules\Accounting\Services\AccountingSetupService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Support\CreatesTenant;
use Tests\Support\CreatesUser;
use Tests\TestCase;

class PurchaseSerialTrackingTest extends TestCase
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
            'name' => 'Serial Supplier',
            'is_active' => true,
        ]);

        $this->warehouse = Warehouse::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Main Warehouse',
            'code' => 'WH-MAIN',
            'is_active' => true,
        ]);
    }

    public function test_direct_purchase_creates_and_posts_serial_tracked_product(): void
    {
        $serialProduct = Product::create([
            'tenant_id' => $this->tenant->id,
            'code' => 'PRD-SER',
            'sku' => 'SKU-SER',
            'name' => 'Serial Phone',
            'slug' => 'serial-phone',
            'product_type' => ProductTypeEnum::PRODUCT,
            'inventory_tracking_type' => InventoryTrackingTypeEnum::SERIAL,
            'is_active' => true,
            'purchase_price' => 500,
            'selling_price' => 800,
        ]);

        $payload = [
            'supplier_uuid' => $this->supplier->uuid,
            'warehouse_uuid' => $this->warehouse->uuid,
            'purchase_date' => now()->toDateString(),
            'items' => [
                [
                    'product_uuid' => $serialProduct->uuid,
                    'quantity' => 2,
                    'unit_cost' => 500,
                    'serials' => ['SN-PHONE-001', 'SN-PHONE-002'],
                ],
            ],
        ];

        $response = $this->postJson(route('purchases.store'), $payload);
        $response->assertStatus(201);

        $purchaseUuid = $response->json('data.uuid');
        $this->assertDatabaseHas('purchase_item_serials', [
            'serial_number' => 'SN-PHONE-001',
        ]);
        $this->assertDatabaseHas('purchase_item_serials', [
            'serial_number' => 'SN-PHONE-002',
        ]);

        $confirmResponse = $this->postJson("/api/purchases/{$purchaseUuid}/confirm");
        $confirmResponse->assertStatus(200);

        $this->assertDatabaseHas('product_serials', [
            'tenant_id' => $this->tenant->id,
            'serial_number' => 'SN-PHONE-001',
            'status' => ProductSerialStatusEnum::AVAILABLE->value,
        ]);
        $this->assertDatabaseHas('product_serials', [
            'tenant_id' => $this->tenant->id,
            'serial_number' => 'SN-PHONE-002',
            'status' => ProductSerialStatusEnum::AVAILABLE->value,
        ]);

        $serial1 = ProductSerial::withoutGlobalScopes()->where('serial_number', 'SN-PHONE-001')->first();
        $this->assertNotNull($serial1);
        $this->assertEquals(ProductSerialStatusEnum::AVAILABLE, $serial1->status);

        $this->assertDatabaseHas('stock_ledgers', [
            'product_id' => $serialProduct->id,
            'product_serial_id' => $serial1->id,
            'quantity_in' => 1,
        ]);
    }

    public function test_serial_tracked_product_fails_validation_if_serial_count_mismatches_quantity(): void
    {
        $serialProduct = Product::create([
            'tenant_id' => $this->tenant->id,
            'code' => 'PRD-SER-ERR',
            'sku' => 'SKU-SER-ERR',
            'name' => 'Serial Error',
            'slug' => 'serial-error',
            'product_type' => ProductTypeEnum::PRODUCT,
            'inventory_tracking_type' => InventoryTrackingTypeEnum::SERIAL,
            'is_active' => true,
            'purchase_price' => 100,
            'selling_price' => 150,
        ]);

        $payload = [
            'supplier_uuid' => $this->supplier->uuid,
            'warehouse_uuid' => $this->warehouse->uuid,
            'purchase_date' => now()->toDateString(),
            'items' => [
                [
                    'product_uuid' => $serialProduct->uuid,
                    'quantity' => 3,
                    'unit_cost' => 100,
                    'serials' => ['SN-ERR-001', 'SN-ERR-002'],
                ],
            ],
        ];

        $response = $this->postJson(route('purchases.store'), $payload);
        $response->assertStatus(422);
    }

    public function test_duplicate_serial_fails_validation(): void
    {
        $serialProduct = Product::create([
            'tenant_id' => $this->tenant->id,
            'code' => 'PRD-SER-DUP',
            'sku' => 'SKU-SER-DUP',
            'name' => 'Serial Dup',
            'slug' => 'serial-dup',
            'product_type' => ProductTypeEnum::PRODUCT,
            'inventory_tracking_type' => InventoryTrackingTypeEnum::SERIAL,
            'is_active' => true,
            'purchase_price' => 100,
            'selling_price' => 150,
        ]);

        $payload = [
            'supplier_uuid' => $this->supplier->uuid,
            'warehouse_uuid' => $this->warehouse->uuid,
            'purchase_date' => now()->toDateString(),
            'items' => [
                [
                    'product_uuid' => $serialProduct->uuid,
                    'quantity' => 2,
                    'unit_cost' => 100,
                    'serials' => ['SN-DUP', 'SN-DUP'],
                ],
            ],
        ];

        $response = $this->postJson(route('purchases.store'), $payload);
        $response->assertStatus(422);
    }

    public function test_purchase_show_includes_serials(): void
    {
        $serialProduct = Product::create([
            'tenant_id' => $this->tenant->id,
            'code' => 'PRD-SER-SHOW',
            'sku' => 'SKU-SER-SHOW',
            'name' => 'Serial Show Item',
            'slug' => 'serial-show-item',
            'product_type' => ProductTypeEnum::PRODUCT,
            'inventory_tracking_type' => InventoryTrackingTypeEnum::SERIAL,
            'is_active' => true,
            'purchase_price' => 100,
            'selling_price' => 200,
        ]);

        $payload = [
            'supplier_uuid' => $this->supplier->uuid,
            'warehouse_uuid' => $this->warehouse->uuid,
            'purchase_date' => now()->toDateString(),
            'items' => [
                [
                    'product_uuid' => $serialProduct->uuid,
                    'quantity' => 1,
                    'unit_cost' => 100,
                    'serials' => ['SN-SHOW-999'],
                ],
            ],
        ];

        $createResponse = $this->postJson(route('purchases.store'), $payload);
        $createResponse->assertStatus(201);
        $purchaseUuid = $createResponse->json('data.uuid');

        $showResponse = $this->getJson("/api/purchases/{$purchaseUuid}");
        $showResponse->assertStatus(200);

        $showResponse->assertJsonPath('data.items.0.serials.0.serial_number', 'SN-SHOW-999');
    }
}

