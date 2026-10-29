<?php

namespace Tests\Feature\Purchase;

use App\Modules\Tenant\Models\Tenant;
use App\Modules\User\Models\User;
use App\Modules\Supplier\Models\Supplier;
use App\Modules\Warehouse\Models\Warehouse;
use App\Modules\Product\Models\Product;
use App\Modules\Product\Models\ProductVariant;
use App\Modules\Product\Enums\ProductTypeEnum;
use App\Modules\Product\Enums\InventoryTrackingTypeEnum;
use App\Modules\Purchase\Models\Purchase;
use App\Modules\Purchase\Enums\PurchaseStatusEnum;
use App\Modules\Accounting\Services\AccountingSetupService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Support\CreatesTenant;
use Tests\Support\CreatesUser;
use Tests\TestCase;

class PurchaseCrudTest extends TestCase
{
    use RefreshDatabase;
    use CreatesTenant;
    use CreatesUser;

    protected Tenant $tenant;
    protected User $user;
    protected Supplier $supplier;
    protected Warehouse $warehouse;
    protected Product $product;
    protected ProductVariant $variant;

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
            'code' => 'PRD-001',
            'sku' => 'SKU-001',
            'name' => 'Standard Widget',
            'slug' => 'standard-widget',
            'product_type' => ProductTypeEnum::PRODUCT,
            'inventory_tracking_type' => InventoryTrackingTypeEnum::NONE,
            'has_variants' => true,
            'is_active' => true,
            'purchase_price' => 100,
            'selling_price' => 150,
        ]);

        $this->variant = ProductVariant::create([
            'tenant_id' => $this->tenant->id,
            'product_id' => $this->product->id,
            'sku' => 'SKU-001-RED',
            'code' => 'VAR-RED',
            'name' => 'Red Widget',
            'purchase_price' => 110,
            'selling_price' => 160,
            'is_active' => true,
        ]);
    }

    public function test_purchase_can_be_created_as_draft_with_uuids(): void
    {
        $payload = [
            'supplier_uuid' => $this->supplier->uuid,
            'warehouse_uuid' => $this->warehouse->uuid,
            'purchase_date' => now()->toDateString(),
            'notes' => 'Test draft purchase',
            'items' => [
                [
                    'product_uuid' => $this->product->uuid,
                    'product_variant_uuid' => $this->variant->uuid,
                    'quantity' => 10,
                    'unit_cost' => 110,
                ],
            ],
        ];

        $response = $this->postJson(route('purchases.store'), $payload);

        $response->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.status', 'draft')
            ->assertJsonPath('data.subtotal', 1100)
            ->assertJsonPath('data.grand_total', 1100)
            ->assertJsonPath('data.due_amount', 1100);

        $this->assertDatabaseHas('purchases', [
            'tenant_id' => $this->tenant->id,
            'supplier_id' => $this->supplier->id,
            'status' => 'draft',
        ]);

        $this->assertDatabaseHas('purchase_items', [
            'tenant_id' => $this->tenant->id,
            'product_id' => $this->product->id,
            'product_variant_id' => $this->variant->id,
            'quantity' => 10,
        ]);
    }

    public function test_purchase_can_be_shown_by_uuid(): void
    {
        $purchase = Purchase::create([
            'tenant_id' => $this->tenant->id,
            'supplier_id' => $this->supplier->id,
            'warehouse_id' => $this->warehouse->id,
            'purchase_no' => 'PUR-TEST-001',
            'purchase_date' => now()->toDateString(),
            'subtotal' => 500,
            'grand_total' => 500,
            'paid_amount' => 0,
            'due_amount' => 500,
            'status' => PurchaseStatusEnum::DRAFT->value,
        ]);

        $response = $this->getJson(route('purchases.show', $purchase->uuid));

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.uuid', $purchase->uuid)
            ->assertJsonPath('data.purchase_no', 'PUR-TEST-001');
    }

    public function test_purchase_can_be_updated_when_in_draft(): void
    {
        $purchase = Purchase::create([
            'tenant_id' => $this->tenant->id,
            'supplier_id' => $this->supplier->id,
            'warehouse_id' => $this->warehouse->id,
            'purchase_no' => 'PUR-TEST-002',
            'purchase_date' => now()->toDateString(),
            'subtotal' => 500,
            'grand_total' => 500,
            'due_amount' => 500,
            'status' => PurchaseStatusEnum::DRAFT->value,
        ]);

        $payload = [
            'purchase_date' => now()->toDateString(),
            'notes' => 'Updated notes',
            'items' => [
                [
                    'product_uuid' => $this->product->uuid,
                    'quantity' => 5,
                    'unit_cost' => 200,
                ],
            ],
        ];

        $response = $this->putJson(route('purchases.update', $purchase->uuid), $payload);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.notes', 'Updated notes')
            ->assertJsonPath('data.grand_total', 1000);
    }

    public function test_purchase_can_be_cancelled(): void
    {
        $purchase = Purchase::create([
            'tenant_id' => $this->tenant->id,
            'supplier_id' => $this->supplier->id,
            'warehouse_id' => $this->warehouse->id,
            'purchase_no' => 'PUR-TEST-003',
            'purchase_date' => now()->toDateString(),
            'subtotal' => 300,
            'grand_total' => 300,
            'due_amount' => 300,
            'status' => PurchaseStatusEnum::DRAFT->value,
        ]);

        $response = $this->postJson(route('purchases.cancel', $purchase->uuid), [
            'cancellation_reason' => 'Duplicate order',
        ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.status', 'cancelled')
            ->assertJsonPath('data.cancellation_reason', 'Duplicate order');
    }

    public function test_validation_fails_for_invalid_supplier(): void
    {
        $payload = [
            'supplier_uuid' => '00000000-0000-0000-0000-000000000000',
            'purchase_date' => now()->toDateString(),
            'items' => [
                [
                    'product_uuid' => $this->product->uuid,
                    'quantity' => 1,
                    'unit_cost' => 10,
                ],
            ],
        ];

        $response = $this->postJson(route('purchases.store'), $payload);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['supplier_uuid']);
    }
}

