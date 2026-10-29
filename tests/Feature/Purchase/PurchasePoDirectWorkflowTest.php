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
use App\Modules\Unit\Models\Unit;
use App\Modules\PurchaseOrder\Models\PurchaseOrder;
use App\Modules\PurchaseOrder\Models\PurchaseOrderItem;
use App\Modules\PurchaseOrder\Enums\PurchaseOrderStatusEnum;
use App\Modules\Purchase\Models\Purchase;
use App\Modules\Accounting\Services\AccountingSetupService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Support\CreatesTenant;
use Tests\Support\CreatesUser;
use Tests\TestCase;

class PurchasePoDirectWorkflowTest extends TestCase
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
    protected Unit $unit;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = $this->createTestTenant();
        $this->user = $this->createTestUser();
        Sanctum::actingAs($this->user);

        app(AccountingSetupService::class)->setup($this->tenant);

        $this->supplier = Supplier::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Direct Parts Co',
            'is_active' => true,
        ]);

        $this->warehouse = Warehouse::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Central Hub',
            'code' => 'WH-CENTRAL',
            'is_active' => true,
        ]);

        $this->unit = Unit::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Piece',
            'code' => 'PCS',
            'short_name' => 'pcs',
            'is_active' => true,
        ]);

        $this->product = Product::create([
            'tenant_id' => $this->tenant->id,
            'code' => 'PRD-SSD',
            'sku' => 'SKU-SSD',
            'name' => 'NVMe SSD',
            'slug' => 'nvme-ssd',
            'product_type' => ProductTypeEnum::PRODUCT,
            'inventory_tracking_type' => InventoryTrackingTypeEnum::NONE,
            'has_variants' => true,
            'is_active' => true,
            'purchase_price' => 6000,
            'selling_price' => 8000,
        ]);

        $this->variant = ProductVariant::create([
            'tenant_id' => $this->tenant->id,
            'product_id' => $this->product->id,
            'sku' => 'SKU-SSD-1TB',
            'code' => 'VAR-1TB',
            'name' => '1TB NVMe SSD',
            'purchase_price' => 6500,
            'selling_price' => 8500,
            'is_active' => true,
        ]);
    }

    public function test_flow_b_po_to_direct_purchase_bill_posts_inventory_and_accounting(): void
    {
        // 1. Create Purchase Order
        $po = PurchaseOrder::create([
            'tenant_id' => $this->tenant->id,
            'supplier_id' => $this->supplier->id,
            'warehouse_id' => $this->warehouse->id,
            'order_number' => 'PO-DIR-001',
            'order_date' => now()->toDateString(),
            'status' => PurchaseOrderStatusEnum::CONFIRMED->value,
        ]);

        $poItem = PurchaseOrderItem::create([
            'tenant_id' => $this->tenant->id,
            'purchase_order_id' => $po->id,
            'product_id' => $this->product->id,
            'product_variant_id' => $this->variant->id,
            'warehouse_id' => $this->warehouse->id,
            'line_type' => 'product',
            'unit_of_measure_id' => $this->unit->id,
            'item_name' => '1TB NVMe SSD',
            'quantity' => 5,
            'unit_price' => 6500,
            'net_unit_price' => 6500,
            'subtotal_amount' => 32500,
            'total_amount' => 32500,
        ]);

        // 2. Create Purchase Bill directly from PO (no GRN)
        $payload = [
            'supplier_uuid' => $this->supplier->uuid,
            'warehouse_uuid' => $this->warehouse->uuid,
            'purchase_order_uuid' => $po->uuid,
            'purchase_date' => now()->toDateString(),
            'items' => [
                [
                    'purchase_order_item_uuid' => $poItem->uuid,
                    'product_uuid' => $this->product->uuid,
                    'product_variant_uuid' => $this->variant->uuid,
                    'quantity' => 5,
                    'unit_cost' => 6500,
                ],
            ],
        ];

        $createResponse = $this->postJson(route('purchases.store'), $payload);
        $createResponse->assertCreated();
        $purchaseUuid = $createResponse->json('data.uuid');

        // 3. Confirm Purchase Bill
        $confirmResponse = $this->postJson("/api/purchases/{$purchaseUuid}/confirm");
        $confirmResponse->assertOk()
            ->assertJsonPath('data.status', 'confirmed');

        // 4. Verify Stock Ledger was created (since no GRN, direct bill introduces stock)
        $this->assertDatabaseHas('stock_ledgers', [
            'tenant_id' => $this->tenant->id,
            'product_id' => $this->product->id,
            'product_variant_id' => $this->variant->id,
            'quantity_in' => 5,
        ]);

        // 5. Verify Accounting Journal Entry was created
        $this->assertDatabaseHas('journal_entries', [
            'tenant_id' => $this->tenant->id,
            'reference_type' => Purchase::class,
        ]);
    }
}

