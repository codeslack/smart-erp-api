<?php

namespace Tests\Feature\Purchase;

use App\Modules\Tenant\Models\Tenant;
use App\Core\Tenant\TenantManager;
use App\Modules\User\Models\User;
use App\Modules\Supplier\Models\Supplier;
use App\Modules\Warehouse\Models\Warehouse;
use App\Modules\Product\Models\Product;
use App\Modules\Product\Models\ProductVariant;
use App\Modules\Product\Enums\ProductTypeEnum;
use App\Modules\Product\Enums\InventoryTrackingTypeEnum;
use App\Modules\Unit\Models\Unit;
use App\Modules\GoodsReceiptNote\Models\GoodsReceiptNote;
use App\Modules\GoodsReceiptNote\Models\GoodsReceiptNoteItem;
use App\Modules\GoodsReceiptNote\Models\GoodsReceiptNoteItemBatch;
use App\Modules\GoodsReceiptNote\Models\GoodsReceiptNoteItemSerial;
use App\Modules\Inventory\Models\ProductStock;
use App\Modules\Inventory\Models\StockLedger;
use App\Modules\Inventory\Models\ProductBatch;
use App\Modules\GoodsReceiptNote\Enums\GoodsReceiptNoteStatusEnum;
use App\Modules\Accounting\Services\AccountingSetupService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Support\CreatesTenant;
use Tests\Support\CreatesUser;
use Tests\TestCase;

class PurchaseGrnAllocationTest extends TestCase
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

    public function test_multiple_bills_per_grn_and_cumulative_quantity_check(): void
    {
        $grn = GoodsReceiptNote::create([
            'tenant_id' => $this->tenant->id,
            'supplier_id' => $this->supplier->id,
            'warehouse_id' => $this->warehouse->id,
            'purchase_order_id' => 1,
            'grn_number' => 'GRN-MULTI-001',
            'grn_date' => now()->toDateString(),
            'status' => GoodsReceiptNoteStatusEnum::CONFIRMED->value,
        ]);

        $grnItem = GoodsReceiptNoteItem::create([
            'tenant_id' => $this->tenant->id,
            'goods_receipt_note_id' => $grn->id,
            'purchase_order_item_id' => 1,
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

        // Bill 1: 6 units (confirmed)
        $bill1 = $this->postJson(route('purchases.store'), [
            'supplier_uuid' => $this->supplier->uuid,
            'goods_receipt_note_uuid' => $grn->uuid,
            'purchase_date' => now()->toDateString(),
            'currency_code' => 'USD',
            'exchange_rate' => 83.25,
            'items' => [
                [
                    'goods_receipt_note_item_uuid' => $grnItem->uuid,
                    'quantity' => 6,
                    'unit_cost' => 4500,
                ],
            ],
        ]);
        $bill1->assertCreated();
        $this->postJson("/api/purchases/{$bill1->json('data.uuid')}/confirm")->assertOk();
        app(TenantManager::class)->setTenant($this->tenant);

        // The first approved bill posts only its six received units.
        $stock = ProductStock::query()
            ->where('tenant_id', $this->tenant->id)
            ->where('product_id', $this->product->id)
            ->where('product_variant_id', $this->variant->id)
            ->where('warehouse_id', $this->warehouse->id)
            ->firstOrFail();

        $this->assertEquals(6, (float) $stock->quantity);
        $this->assertEqualsWithDelta(
            374625,
            (float) $stock->average_cost,
            0.01
        );

        $grn->refresh();
        $this->assertSame('USD', $grn->inventory_currency_code);
        $this->assertEquals(83.25, (float) $grn->inventory_exchange_rate);

        $this->assertSame(
            1,
            StockLedger::query()
                ->where('tenant_id', $this->tenant->id)
                ->where('referenceable_type', Purchase::class)
                ->where('transaction_type', 'PURCHASE')
                ->count()
        );

        $this->assertEquals(
            6,
            (float) StockLedger::query()
                ->where('referenceable_type', Purchase::class)
                ->where('transaction_type', 'PURCHASE')
                ->sum('quantity_in')
        );

        // Bill 2: 4 units (confirmed) -> Total = 10 units
        $bill2 = $this->postJson(route('purchases.store'), [
            'supplier_uuid' => $this->supplier->uuid,
            'goods_receipt_note_uuid' => $grn->uuid,
            'purchase_date' => now()->toDateString(),
            'currency_code' => 'USD',
            'exchange_rate' => 83.25,
            'items' => [
                [
                    'goods_receipt_note_item_uuid' => $grnItem->uuid,
                    'quantity' => 4,
                    'unit_cost' => 4500,
                ],
            ],
        ]);
        $bill2->assertCreated();
        $this->postJson("/api/purchases/{$bill2->json('data.uuid')}/confirm")->assertOk();
        app(TenantManager::class)->setTenant($this->tenant);

        // The second bill posts only its remaining GRN quantity.
        $stock->refresh();
        $this->assertEquals(10, (float) $stock->quantity);

        $this->assertSame(
            2,
            StockLedger::query()
                ->where('tenant_id', $this->tenant->id)
                ->where('referenceable_type', Purchase::class)
                ->where('transaction_type', 'PURCHASE')
                ->count()
        );

        $this->assertEquals(
            10,
            (float) StockLedger::query()
                ->where('referenceable_type', Purchase::class)
                ->where('transaction_type', 'PURCHASE')
                ->sum('quantity_in')
        );

        // Bill 3: tries to confirm 1 more unit -> Should fail with 422
        $bill3 = $this->postJson(route('purchases.store'), [
            'supplier_uuid' => $this->supplier->uuid,
            'goods_receipt_note_uuid' => $grn->uuid,
            'purchase_date' => now()->toDateString(),
            'items' => [
                [
                    'goods_receipt_note_item_uuid' => $grnItem->uuid,
                    'quantity' => 1,
                    'unit_cost' => 4500,
                ],
            ],
        ]);
        $bill3->assertCreated();

        $confirmFail = $this->postJson("/api/purchases/{$bill3->json('data.uuid')}/confirm");
        $confirmFail->assertStatus(422);
    }

    public function test_grn_batch_is_created_and_valued_on_purchase_confirmation(): void
    {
        $this->product->update([
            'inventory_tracking_type' => InventoryTrackingTypeEnum::BATCH,
        ]);

        $grn = GoodsReceiptNote::create([
            'tenant_id' => $this->tenant->id,
            'supplier_id' => $this->supplier->id,
            'warehouse_id' => $this->warehouse->id,
            'purchase_order_id' => 1,
            'grn_number' => 'GRN-BATCH-POST-001',
            'grn_date' => now()->toDateString(),
            'status' => GoodsReceiptNoteStatusEnum::CONFIRMED->value,
        ]);

        $grnItem = GoodsReceiptNoteItem::create([
            'tenant_id' => $this->tenant->id,
            'goods_receipt_note_id' => $grn->id,
            'purchase_order_item_id' => 1,
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

        GoodsReceiptNoteItemBatch::create([
            'tenant_id' => $this->tenant->id,
            'goods_receipt_note_item_id' => $grnItem->id,
            'batch_no' => 'GRN-BATCH-POST-001',
            'quantity' => 10,
            'expiry_date' => now()->addYear()->toDateString(),
        ]);

        $bill = $this->postJson(route('purchases.store'), [
            'supplier_uuid' => $this->supplier->uuid,
            'warehouse_uuid' => $this->warehouse->uuid,
            'goods_receipt_note_uuid' => $grn->uuid,
            'purchase_date' => now()->toDateString(),
            'items' => [[
                'goods_receipt_note_item_uuid' => $grnItem->uuid,
                'quantity' => 10,
                'unit_cost' => 4500,
            ]],
        ])->assertCreated();

        $this->postJson("/api/purchases/{$bill->json('data.uuid')}/confirm")
            ->assertOk();

        app(TenantManager::class)->setTenant($this->tenant);

        $batch = ProductBatch::query()
            ->where('batch_no', 'GRN-BATCH-POST-001')
            ->firstOrFail();

        $this->assertSame('ACTIVE', $batch->status->value);
        $this->assertEquals(10, (float) $batch->original_quantity);
        $this->assertEquals(10, (float) $batch->remaining_quantity);
        $this->assertSame(
            GoodsReceiptNote::class,
            $batch->sourceable_type
        );
        $this->assertSame($grn->id, $batch->sourceable_id);
        $this->assertSame(
            now()->addYear()->toDateString(),
            $batch->expiry_date->toDateString()
        );

        $stock = ProductStock::query()
            ->where('product_id', $this->product->id)
            ->where('product_variant_id', $this->variant->id)
            ->where('warehouse_id', $this->warehouse->id)
            ->firstOrFail();

        $this->assertEquals(10, (float) $stock->quantity);
    }

    public function test_grn_serials_are_registered_on_purchase_confirmation(): void
    {
        $this->product->update([
            'inventory_tracking_type' => InventoryTrackingTypeEnum::SERIAL,
        ]);

        $grn = GoodsReceiptNote::create([
            'tenant_id' => $this->tenant->id,
            'supplier_id' => $this->supplier->id,
            'warehouse_id' => $this->warehouse->id,
            'purchase_order_id' => 1,
            'grn_number' => 'GRN-SERIAL-POST-001',
            'grn_date' => now()->toDateString(),
            'status' => GoodsReceiptNoteStatusEnum::CONFIRMED->value,
        ]);

        $grnItem = GoodsReceiptNoteItem::create([
            'tenant_id' => $this->tenant->id,
            'goods_receipt_note_id' => $grn->id,
            'purchase_order_item_id' => 1,
            'product_id' => $this->product->id,
            'product_variant_id' => $this->variant->id,
            'warehouse_id' => $this->warehouse->id,
            'unit_id' => $this->unit->id,
            'quantity_received' => 2,
            'base_quantity' => 2,
            'unit_cost' => 4500,
            'base_unit_cost' => 4500,
            'line_total' => 9000,
        ]);

        foreach (['GRN-SERIAL-POST-001', 'GRN-SERIAL-POST-002'] as $serialNumber) {
            GoodsReceiptNoteItemSerial::create([
                'tenant_id' => $this->tenant->id,
                'goods_receipt_note_item_id' => $grnItem->id,
                'serial_number' => $serialNumber,
            ]);
        }

        $bill = $this->postJson(route('purchases.store'), [
            'supplier_uuid' => $this->supplier->uuid,
            'warehouse_uuid' => $this->warehouse->uuid,
            'goods_receipt_note_uuid' => $grn->uuid,
            'purchase_date' => now()->toDateString(),
            'items' => [[
                'goods_receipt_note_item_uuid' => $grnItem->uuid,
                'quantity' => 2,
                'unit_cost' => 4500,
            ]],
        ])->assertCreated();

        $this->postJson("/api/purchases/{$bill->json('data.uuid')}/confirm")
            ->assertOk();

        app(TenantManager::class)->setTenant($this->tenant);

        $this->assertDatabaseHas('product_serials', [
            'tenant_id' => $this->tenant->id,
            'serial_number' => 'GRN-SERIAL-POST-001',
            'sourceable_type' => GoodsReceiptNote::class,
            'sourceable_id' => $grn->id,
            'status' => 'AVAILABLE',
        ]);

        $this->assertDatabaseHas('product_serials', [
            'tenant_id' => $this->tenant->id,
            'serial_number' => 'GRN-SERIAL-POST-002',
            'sourceable_type' => GoodsReceiptNote::class,
            'sourceable_id' => $grn->id,
            'status' => 'AVAILABLE',
        ]);

        $stock = ProductStock::query()
            ->where('product_id', $this->product->id)
            ->where('product_variant_id', $this->variant->id)
            ->where('warehouse_id', $this->warehouse->id)
            ->firstOrFail();

        $this->assertEquals(2, (float) $stock->quantity);
    }

}
