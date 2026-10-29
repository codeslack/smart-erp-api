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
use App\Modules\GoodsReceiptNote\Models\GoodsReceiptNote;
use App\Modules\GoodsReceiptNote\Models\GoodsReceiptNoteItem;
use App\Modules\GoodsReceiptNote\Enums\GoodsReceiptNoteStatusEnum;
use App\Modules\Purchase\Models\Purchase;
use App\Modules\Accounting\Services\AccountingSetupService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Support\CreatesTenant;
use Tests\Support\CreatesUser;
use Tests\TestCase;

class PurchaseWorkflowTest extends TestCase
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
            'name' => 'Apex Parts Ltd',
            'is_active' => true,
        ]);

        $this->warehouse = Warehouse::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'North Hub',
            'code' => 'WH-NORTH',
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
            'code' => 'PRD-RAM',
            'sku' => 'SKU-RAM',
            'name' => 'DDR5 RAM',
            'slug' => 'ddr5-ram',
            'product_type' => ProductTypeEnum::PRODUCT,
            'inventory_tracking_type' => InventoryTrackingTypeEnum::NONE,
            'has_variants' => true,
            'is_active' => true,
            'purchase_price' => 4000,
            'selling_price' => 5000,
        ]);

        $this->variant = ProductVariant::create([
            'tenant_id' => $this->tenant->id,
            'product_id' => $this->product->id,
            'sku' => 'SKU-RAM-16GB',
            'code' => 'VAR-16GB',
            'name' => '16GB DDR5',
            'purchase_price' => 4500,
            'selling_price' => 5500,
            'is_active' => true,
        ]);
    }

    public function test_flow_a_po_to_grn_to_purchase_bill(): void
    {
        // 1. Create Purchase Order
        $po = PurchaseOrder::create([
            'tenant_id' => $this->tenant->id,
            'supplier_id' => $this->supplier->id,
            'warehouse_id' => $this->warehouse->id,
            'order_number' => 'PO-001',
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
            'item_name' => '16GB DDR5',
            'quantity' => 10,
            'unit_price' => 4500,
            'net_unit_price' => 4500,
            'subtotal_amount' => 45000,
            'total_amount' => 45000,
        ]);

        // 2. Create and Confirm GRN
        $grn = GoodsReceiptNote::create([
            'tenant_id' => $this->tenant->id,
            'supplier_id' => $this->supplier->id,
            'warehouse_id' => $this->warehouse->id,
            'purchase_order_id' => $po->id,
            'grn_number' => 'GRN-001',
            'grn_date' => now()->toDateString(),
            'status' => GoodsReceiptNoteStatusEnum::CONFIRMED->value,
        ]);

        $grnItem = GoodsReceiptNoteItem::create([
            'tenant_id' => $this->tenant->id,
            'goods_receipt_note_id' => $grn->id,
            'purchase_order_item_id' => $poItem->id,
            'product_id' => $this->product->id,
            'product_variant_id' => $this->variant->id,
            'warehouse_id' => $this->warehouse->id,
            'unit_id' => $this->unit->id,
            'quantity_received' => 10,
            'base_quantity' => 10,
            'unit_cost' => 4500,
            'base_unit_cost' => 4500,
            'line_total' => 45000,
        ]);

        // 3. Create Purchase Bill referencing GRN
        $payload = [
            'supplier_uuid' => $this->supplier->uuid,
            'warehouse_uuid' => $this->warehouse->uuid,
            'purchase_order_uuid' => $po->uuid,
            'goods_receipt_note_uuid' => $grn->uuid,
            'purchase_date' => now()->toDateString(),
            'items' => [
                [
                    'goods_receipt_note_item_uuid' => $grnItem->uuid,
                    'quantity' => 10,
                    'unit_cost' => 4500,
                ],
            ],
        ];

        $createResponse = $this->postJson(route('purchases.store'), $payload);
        $createResponse->assertCreated();
        $purchaseUuid = $createResponse->json('data.uuid');

        // Confirm Purchase Bill
        $confirmResponse = $this->postJson("/api/purchases/{$purchaseUuid}/confirm");
        $confirmResponse->assertOk()
            ->assertJsonPath('data.status', 'confirmed');

        // Verify GRN-linked Purchase Bill does NOT post stock ledger (stock belongs to GRN)
        $this->assertDatabaseMissing('stock_ledgers', [
            'tenant_id' => $this->tenant->id,
            'referenceable_type' => Purchase::class,
        ]);

        // Verify balanced Accounting Journal Entry was created
        $this->assertDatabaseHas('journal_entries', [
            'tenant_id' => $this->tenant->id,
            'reference_type' => Purchase::class,
        ]);
    }
}
