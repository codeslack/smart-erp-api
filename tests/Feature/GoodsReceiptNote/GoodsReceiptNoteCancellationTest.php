<?php

namespace Tests\Feature\GoodsReceiptNote;

use Tests\TestCase;

use Illuminate\Foundation\Testing\RefreshDatabase;

use Tests\Support\CreatesTenant;
use Tests\Support\CreatesUser;
use Tests\Support\CreatesProduct;

use App\Core\Tenant\TenantManager;
use App\Core\Exceptions\BusinessException;

use App\Modules\Tenant\Models\Tenant;
use App\Modules\User\Models\User;
use App\Modules\Tenant\Services\TenantSetupService;

use App\Modules\Settings\Models\Setting;


use App\Modules\Supplier\Models\Supplier;
use App\Modules\Warehouse\Models\Warehouse;
use App\Modules\Inventory\Models\ProductStock;
use App\Modules\Unit\Models\Unit;
use App\Modules\Product\Models\Product;
use App\Modules\ProductUnit\Services\ProductUnitService;

use App\Modules\PurchaseOrder\Enums\PurchaseOrderStatusEnum;
use App\Modules\PurchaseOrder\Models\PurchaseOrder;
use App\Modules\PurchaseOrder\Models\PurchaseOrderItem;

use App\Modules\GoodsReceiptNote\Enums\GoodsReceiptNoteStatusEnum;
use App\Modules\GoodsReceiptNote\Models\GoodsReceiptNote;
use App\Modules\GoodsReceiptNote\Models\GoodsReceiptNoteItem;
use App\Modules\GoodsReceiptNote\Services\Lifecycle\GoodsReceiptNoteCancellationService;
use App\Modules\GoodsReceiptNote\Services\Lifecycle\GoodsReceiptNoteConfirmationService;
use App\Modules\Purchase\Services\PurchaseService;
use Laravel\Sanctum\Sanctum;

class GoodsReceiptNoteCancellationTest extends TestCase
{
    use RefreshDatabase;
    use CreatesTenant;
    use CreatesUser;
    use CreatesProduct;

    protected Tenant $tenant;

    protected User $user;

    protected Unit $baseUnit;

    protected Unit $purchaseUnit;

    protected Product $product;

    protected Supplier $supplier;

    protected Warehouse $warehouse;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = $this->createTestTenant();

        app(TenantManager::class)
            ->setTenant($this->tenant);

        app(TenantSetupService::class)
            ->setup($this->tenant);

        $this->user = $this->createTestUser();
        Sanctum::actingAs($this->user);

        $units = Unit::query()
            ->orderBy('id')
            ->limit(2)
            ->get();

        $this->assertCount(
            2,
            $units,
            'Tenant setup must provide at least two units.'
        );

        $this->baseUnit = $units->get(0);
        $this->purchaseUnit = $units->get(1);

        $this->product = $this->createProduct([
            'unit_id' => $this->baseUnit->id,
        ]);

        $this->supplier = Supplier::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Test Supplier',
            'is_active' => true,
        ]);

        $this->warehouse = Warehouse::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Test Warehouse',
            'code' => 'WH-GRN-CANCEL',
            'is_active' => true,
        ]);

        app(ProductUnitService::class)
            ->syncBaseUnit($this->product);

        app(ProductUnitService::class)->create([
            'product_id' => $this->product->id,
            'unit_id' => $this->purchaseUnit->id,
            'conversion_factor' => 10,
            'is_purchase_unit' => true,
            'is_sales_unit' => true,
            'is_active' => true,
        ]);
    }

    public function test_confirmed_grn_can_be_cancelled(): void
    {
        [$grn, $poItem] = $this->createConfirmedGrn(
            orderedQuantity: 10,
            receivedBaseQuantity: 50
        );

        $cancelled = app(
            GoodsReceiptNoteCancellationService::class
        )->cancel($grn);

        $this->assertSame(
            GoodsReceiptNoteStatusEnum::CANCELLED,
            $cancelled->status
        );

        $poItem->refresh();

        $this->assertEquals(
            0,
            (float) $poItem->received_quantity
        );
    }

    public function test_grn_cancellation_does_not_touch_inventory_before_purchase_approval(): void
    {
        [$grn] = $this->createConfirmedGrn(
            orderedQuantity: 10,
            receivedBaseQuantity: 50
        );

        $this->assertDatabaseMissing('product_stocks', [
            'tenant_id' => $this->tenant->id,
            'product_id' => $this->product->id,
            'warehouse_id' => $this->warehouse->id,
        ]);

        $this->assertDatabaseMissing('stock_ledgers', [
            'tenant_id' => $this->tenant->id,
            'referenceable_type' => GoodsReceiptNote::class,
            'referenceable_id' => $grn->id,
        ]);

        app(GoodsReceiptNoteCancellationService::class)->cancel($grn);

        $this->assertSame(
            GoodsReceiptNoteStatusEnum::CANCELLED,
            $grn->fresh()->status
        );

        $this->assertDatabaseMissing('product_stocks', [
            'tenant_id' => $this->tenant->id,
            'product_id' => $this->product->id,
            'warehouse_id' => $this->warehouse->id,
        ]);

        $this->assertDatabaseMissing('stock_ledgers', [
            'tenant_id' => $this->tenant->id,
            'referenceable_type' => GoodsReceiptNote::class,
            'referenceable_id' => $grn->id,
        ]);
    }

    public function test_grn_cannot_be_cancelled_after_linked_purchase_confirmation(): void
    {
        Setting::updateOrCreate(
            [
                'tenant_id' => $this->tenant->id,
                'group' => 'inventory',
                'key' => 'costing_method',
            ],
            [
                'value' => 'FIFO',
            ]
        );

        [$grn] = $this->createConfirmedGrn(
            orderedQuantity: 10,
            receivedBaseQuantity: 50
        );

        $grnItem = $grn->items()->firstOrFail();

        $purchaseService = app(PurchaseService::class);
        $purchase = $purchaseService->create([
            'supplier_uuid' => $this->supplier->uuid,
            'warehouse_uuid' => $this->warehouse->uuid,
            'goods_receipt_note_uuid' => $grn->uuid,
            'purchase_date' => now()->toDateString(),
            'items' => [[
                'goods_receipt_note_item_uuid' => $grnItem->uuid,
                'quantity' => 50,
                'unit_cost' => 50,
            ]],
        ]);

        $purchaseService->confirm($purchase);

        $stock = ProductStock::query()
            ->where('product_id', $this->product->id)
            ->where('warehouse_id', $this->warehouse->id)
            ->firstOrFail();

        $this->assertEquals(50, (float) $stock->quantity);

        $this->expectException(BusinessException::class);
        $this->expectExceptionMessage(
            'A goods receipt note cannot be cancelled after a linked Purchase has been confirmed.'
        );

        app(GoodsReceiptNoteCancellationService::class)->cancel($grn);
    }

    public function test_cancellation_changes_partial_po_back_to_confirmed(): void
    {
        [$grn, $poItem] = $this->createConfirmedGrn(
            orderedQuantity: 10,
            receivedBaseQuantity: 50
        );

        $purchaseOrder = $poItem->purchaseOrder;

        $this->assertSame(
            PurchaseOrderStatusEnum::PARTIALLY_RECEIVED->value,
            $purchaseOrder->fresh()->status
        );

        app(
            GoodsReceiptNoteCancellationService::class
        )->cancel($grn);

        $purchaseOrder->refresh();

        $this->assertSame(
            PurchaseOrderStatusEnum::CONFIRMED->value,
            $purchaseOrder->status
        );
    }

    public function test_cancellation_of_fully_received_po_returns_it_to_confirmed(): void
    {
        [$grn, $poItem] = $this->createConfirmedGrn(
            orderedQuantity: 10,
            receivedBaseQuantity: 100
        );

        $purchaseOrder = $poItem->purchaseOrder;

        $this->assertSame(
            PurchaseOrderStatusEnum::FULLY_RECEIVED->value,
            $purchaseOrder->fresh()->status
        );

        app(
            GoodsReceiptNoteCancellationService::class
        )->cancel($grn);

        $purchaseOrder->refresh();

        $this->assertSame(
            PurchaseOrderStatusEnum::CONFIRMED->value,
            $purchaseOrder->status
        );

        $poItem->refresh();

        $this->assertEquals(
            0,
            (float) $poItem->received_quantity
        );
    }

    public function test_cancellation_converts_base_quantity_back_to_purchase_order_unit(): void
    {
        /*
         * PO:
         *   10 BOX
         *
         * BOX:
         *   1 BOX = 10 PCS
         *
         * GRN:
         *   50 PCS = 5 BOX
         *
         * After confirmation:
         *   received_quantity = 5 BOX
         *
         * After cancellation:
         *   received_quantity = 0 BOX
         */

        [$grn, $poItem] = $this->createConfirmedGrn(
            orderedQuantity: 10,
            receivedBaseQuantity: 50
        );

        $this->assertEquals(
            5,
            (float) $poItem->fresh()->received_quantity
        );

        app(
            GoodsReceiptNoteCancellationService::class
        )->cancel($grn);

        $poItem->refresh();

        $this->assertEquals(
            0,
            (float) $poItem->received_quantity
        );
    }

    public function test_confirmed_grn_cannot_be_cancelled_twice(): void
    {
        [$grn] = $this->createConfirmedGrn(
            orderedQuantity: 10,
            receivedBaseQuantity: 50
        );

        app(
            GoodsReceiptNoteCancellationService::class
        )->cancel($grn);

        $this->expectException(BusinessException::class);

        app(
            GoodsReceiptNoteCancellationService::class
        )->cancel($grn->fresh());
    }

    public function test_draft_grn_cannot_be_cancelled(): void
    {
        [$grn] = $this->createDraftGrn(
            orderedQuantity: 10,
            receivedBaseQuantity: 50
        );

        $this->expectException(BusinessException::class);

        app(
            GoodsReceiptNoteCancellationService::class
        )->cancel($grn);
    }

    public function test_failed_cancellation_keeps_grn_confirmed(): void
    {
        [$grn, $poItem] = $this->createConfirmedGrn(
            orderedQuantity: 10,
            receivedBaseQuantity: 50
        );

        /*
         * Simulate an inconsistent PO cache.
         *
         * The GRN represents 5 BOX, but the PO currently
         * has only 2 BOX received. Cancellation must fail
         * instead of creating a negative quantity.
         */
        $poItem->update([
            'received_quantity' => 2,
        ]);

        $this->expectException(BusinessException::class);

        try {
            app(
                GoodsReceiptNoteCancellationService::class
            )->cancel($grn);
        } finally {
            $grn->refresh();

            $this->assertSame(
                GoodsReceiptNoteStatusEnum::CONFIRMED,
                $grn->status
            );
        }
    }

    protected function createConfirmedGrn(
        float $orderedQuantity,
        float $receivedBaseQuantity
    ): array {
        [$grn, $poItem] = $this->createDraftGrn(
            $orderedQuantity,
            $receivedBaseQuantity
        );

        app(
            GoodsReceiptNoteConfirmationService::class
        )->confirm($grn);

        $poItem->refresh();

        return [
            $grn->fresh(),
            $poItem,
        ];
    }

    protected function createDraftGrn(
        float $orderedQuantity,
        float $receivedBaseQuantity
    ): array {
        $purchaseOrder = PurchaseOrder::create([
            'tenant_id' => $this->tenant->id,
            'order_number' => 'PO-CANCEL-TEST-' . uniqid(),
            'order_date' => now()->toDateString(),
            'expected_delivery_date' => now()
                ->addDays(7)
                ->toDateString(),
            'supplier_id' => $this->supplier->id,
            'warehouse_id' => $this->warehouse->id,
            'status' => PurchaseOrderStatusEnum::CONFIRMED->value,
            'currency_code' => 'INR',
            'exchange_rate' => 1,
            'subtotal_amount' => $orderedQuantity * 500,
            'discount_amount' => 0,
            'additional_charge_amount' => 0,
            'tax_total_amount' => 0,
            'rounding_adjustment' => 0,
            'grand_total_amount' => $orderedQuantity * 500,
        ]);

        $poItem = PurchaseOrderItem::create([
            'tenant_id' => $this->tenant->id,
            'purchase_order_id' => $purchaseOrder->id,
            'product_id' => $this->product->id,
            'warehouse_id' => $this->warehouse->id,
            'line_type' => 'product',
            'item_name' => 'Test Product',
            'unit_of_measure_id' => $this->purchaseUnit->id,
            'quantity' => $orderedQuantity,
            'received_quantity' => 0,
            'billed_quantity' => 0,
            'cancelled_quantity' => 0,
            'unit_price' => 500,
            'discount_type' => 'fixed',
            'discount_rate' => 0,
            'discount_amount' => 0,
            'net_unit_price' => 500,
            'subtotal_amount' => $orderedQuantity * 500,
            'tax_base_amount' => $orderedQuantity * 500,
            'tax_total_amount' => 0,
            'total_amount' => $orderedQuantity * 500,
            'is_tax_inclusive' => false,
        ]);

        $grn = GoodsReceiptNote::create([
            'tenant_id' => $this->tenant->id,
            'purchase_order_id' => $purchaseOrder->id,
            'supplier_id' => $purchaseOrder->supplier_id,
            'warehouse_id' => $purchaseOrder->warehouse_id,
            'grn_number' => 'GRN-CANCEL-TEST-' . uniqid(),
            'grn_date' => now()->toDateString(),
            'status' => GoodsReceiptNoteStatusEnum::DRAFT->value,
            'total_received_valuation' =>
            $receivedBaseQuantity * 50,
        ]);

        GoodsReceiptNoteItem::create([
            'tenant_id' => $this->tenant->id,
            'goods_receipt_note_id' => $grn->id,
            'purchase_order_item_id' => $poItem->id,
            'product_id' => $poItem->product_id,
            'warehouse_id' => $poItem->warehouse_id,
            'unit_id' => $this->baseUnit->id,
            'quantity_received' => $receivedBaseQuantity,
            'base_quantity' => $receivedBaseQuantity,
            'unit_cost' => 50,
            'base_unit_cost' => 50,
            'line_total' => $receivedBaseQuantity * 50,
            'tax_rate' => 0,
            'tax_amount' => 0,
        ]);

        return [
            $grn->fresh(),
            $poItem->fresh(),
        ];
    }
}
