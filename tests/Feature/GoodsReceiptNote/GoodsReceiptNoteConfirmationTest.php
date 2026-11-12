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

use App\Modules\Supplier\Models\Supplier;
use App\Modules\Warehouse\Models\Warehouse;
use App\Modules\Unit\Models\Unit;
use App\Modules\Product\Models\Product;
use App\Modules\ProductUnit\Models\ProductUnit;
use App\Modules\ProductUnit\Services\ProductUnitService;

use App\Modules\PurchaseOrder\Enums\PurchaseOrderStatusEnum;
use App\Modules\PurchaseOrder\Models\PurchaseOrder;
use App\Modules\PurchaseOrder\Models\PurchaseOrderItem;

use App\Modules\GoodsReceiptNote\Enums\GoodsReceiptNoteStatusEnum;
use App\Modules\GoodsReceiptNote\Models\GoodsReceiptNote;
use App\Modules\GoodsReceiptNote\Models\GoodsReceiptNoteItem;
use App\Modules\GoodsReceiptNote\Services\Lifecycle\GoodsReceiptNoteConfirmationService;

class GoodsReceiptNoteConfirmationTest extends TestCase
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
            'code' => 'WH-GRN-TEST',
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

    public function test_draft_grn_can_be_confirmed(): void
    {
        [$grn, $poItem] = $this->createGrn(
            orderedQuantity: 10,
            receivedQuantity: 50
        );

        $confirmed = app(
            GoodsReceiptNoteConfirmationService::class
        )->confirm($grn);

        $this->assertSame(
            GoodsReceiptNoteStatusEnum::CONFIRMED,
            $confirmed->status
        );

        $poItem->refresh();

        $this->assertEquals(
            5,
            (float) $poItem->received_quantity
        );

        // GRN confirmation updates PO receiving only; Purchase approval posts stock.
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

    public function test_confirmed_grn_cannot_be_confirmed_again(): void
    {
        [$grn] = $this->createGrn(
            orderedQuantity: 10,
            receivedQuantity: 50
        );

        app(
            GoodsReceiptNoteConfirmationService::class
        )->confirm($grn);

        $this->expectException(BusinessException::class);

        app(
            GoodsReceiptNoteConfirmationService::class
        )->confirm($grn->fresh());
    }

    public function test_grn_cannot_receive_more_than_pending_quantity(): void
    {
        [$grn] = $this->createGrn(
            orderedQuantity: 10,
            receivedQuantity: 101
        );

        $this->expectException(BusinessException::class);

        app(
            GoodsReceiptNoteConfirmationService::class
        )->confirm($grn);
    }

    public function test_multiple_grns_can_receive_same_purchase_order_progressively(): void
    {
        [$grn1, $poItem] = $this->createGrn(
            orderedQuantity: 10,
            receivedQuantity: 40
        );

        app(
            GoodsReceiptNoteConfirmationService::class
        )->confirm($grn1);

        $poItem->refresh();

        $this->assertEquals(
            4,
            (float) $poItem->received_quantity
        );

        [$grn2] = $this->createGrnForExistingItem(
            $poItem,
            60
        );

        app(
            GoodsReceiptNoteConfirmationService::class
        )->confirm($grn2);

        $poItem->refresh();

        $this->assertEquals(
            10,
            (float) $poItem->received_quantity
        );
    }

    public function test_purchase_order_becomes_fully_received(): void
    {
        [$grn, $poItem] = $this->createGrn(
            orderedQuantity: 10,
            receivedQuantity: 100
        );

        $purchaseOrder = $poItem->purchaseOrder;

        app(
            GoodsReceiptNoteConfirmationService::class
        )->confirm($grn);

        $purchaseOrder->refresh();

        $this->assertSame(
            PurchaseOrderStatusEnum::FULLY_RECEIVED->value,
            $purchaseOrder->status
        );
    }

    public function test_partial_receipt_updates_purchase_order_status(): void
    {
        [$grn, $poItem] = $this->createGrn(
            orderedQuantity: 10,
            receivedQuantity: 40
        );

        $purchaseOrder = $poItem->purchaseOrder;

        app(
            GoodsReceiptNoteConfirmationService::class
        )->confirm($grn);

        $purchaseOrder->refresh();

        $this->assertSame(
            PurchaseOrderStatusEnum::PARTIALLY_RECEIVED->value,
            $purchaseOrder->status
        );
    }

    public function test_received_quantity_is_stored_in_purchase_order_unit(): void
    {
        /*
         * PO:
         *   10 BOX
         *
         * BOX:
         *   1 BOX = 10 PCS
         *
         * GRN:
         *   30 PCS
         *
         * PO received cache must become:
         *   3 BOX
         */

        [$grn, $poItem] = $this->createGrn(
            orderedQuantity: 10,
            receivedQuantity: 30
        );

        app(
            GoodsReceiptNoteConfirmationService::class
        )->confirm($grn);

        $poItem->refresh();

        $this->assertEquals(
            3,
            (float) $poItem->received_quantity
        );
    }

    public function test_second_grn_cannot_exceed_remaining_base_quantity(): void
    {
        /*
         * PO = 10 BOX = 100 PCS.
         *
         * First GRN = 60 PCS = 6 BOX.
         * Second GRN = 41 PCS.
         *
         * Remaining = 40 PCS.
         * Second GRN must fail.
         */

        [$grn1, $poItem] = $this->createGrn(
            orderedQuantity: 10,
            receivedQuantity: 60
        );

        app(
            GoodsReceiptNoteConfirmationService::class
        )->confirm($grn1);

        [$grn2] = $this->createGrnForExistingItem(
            $poItem,
            41
        );

        $this->expectException(BusinessException::class);

        app(
            GoodsReceiptNoteConfirmationService::class
        )->confirm($grn2);
    }

    public function test_failed_confirmation_keeps_grn_as_draft(): void
    {
        [$grn, $poItem] = $this->createGrn(
            orderedQuantity: 10,
            receivedQuantity: 101
        );

        $exceptionThrown = false;

        try {
            app(
                GoodsReceiptNoteConfirmationService::class
            )->confirm($grn);
        } catch (BusinessException) {
            $exceptionThrown = true;
        }

        $this->assertTrue(
            $exceptionThrown,
            'Confirmation must fail when received quantity exceeds the ordered quantity.'
        );

        $grn->refresh();
        $poItem->refresh();

        $this->assertSame(
            GoodsReceiptNoteStatusEnum::DRAFT,
            $grn->status
        );

        $this->assertEquals(
            0,
            (float) $poItem->received_quantity
        );
    }

    protected function createGrn(
        float $orderedQuantity,
        float $receivedQuantity
    ): array {
        $purchaseOrder = PurchaseOrder::create([
            'tenant_id' => $this->tenant->id,
            'order_number' => 'PO-TEST-' . uniqid(),
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

        return $this->createGrnForExistingItem(
            $poItem,
            $receivedQuantity
        );
    }

    protected function createGrnForExistingItem(
        PurchaseOrderItem $poItem,
        float $receivedBaseQuantity
    ): array {
        $purchaseOrder = $poItem->purchaseOrder;

        $grn = GoodsReceiptNote::create([
            'tenant_id' => $this->tenant->id,
            'purchase_order_id' => $purchaseOrder->id,
            'supplier_id' => $purchaseOrder->supplier_id,
            'warehouse_id' => $purchaseOrder->warehouse_id,
            'grn_number' => 'GRN-TEST-' . uniqid(),
            'grn_date' => now()->toDateString(),
            'status' => GoodsReceiptNoteStatusEnum::DRAFT->value,
            'total_received_valuation' =>
            $receivedBaseQuantity * 50,
        ]);

        $grnItem = GoodsReceiptNoteItem::create([
            'tenant_id' => $this->tenant->id,
            'goods_receipt_note_id' => $grn->id,
            'purchase_order_item_id' => $poItem->id,
            'product_id' => $poItem->product_id,
            'warehouse_id' => $poItem->warehouse_id,
            'unit_id' => $this->baseUnit->id,

            /*
             * GRN quantities are authoritative in base units.
             *
             * 30 PCS = 3 BOX for this product.
             */
            'quantity_received' => $receivedBaseQuantity,
            'base_quantity' => $receivedBaseQuantity,

            'unit_cost' => 50,
            'base_unit_cost' => 50,
            'line_total' => $receivedBaseQuantity * 50,
            'tax_rate' => 0,
            'tax_amount' => 0,
        ]);

        return [
            $grn,
            $poItem,
            $grnItem,
        ];
    }
}
