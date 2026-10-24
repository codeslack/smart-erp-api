<?php

namespace Tests\Feature\GoodsReceiptNote;

use Tests\TestCase;

use Illuminate\Foundation\Testing\RefreshDatabase;

use Tests\Support\CreatesTenant;
use Tests\Support\CreatesProduct;
use Tests\Support\CreatesWarehouse;

use App\Core\Tenant\TenantManager;
use App\Core\Exceptions\BusinessException;

use App\Modules\Tenant\Models\Tenant;
use App\Modules\Product\Models\Product;
use App\Modules\Product\Models\ProductVariant;
use App\Modules\Warehouse\Models\Warehouse;
use App\Modules\Supplier\Models\Supplier;
use App\Modules\Unit\Models\Unit;

use App\Modules\Accounting\Services\AccountingSetupService;
use App\Modules\Tenant\Services\TenantSetupService;

use App\Modules\ProductUnit\Services\ProductUnitService;
use App\Modules\ProductUnit\Models\ProductUnit;

use App\Modules\PurchaseOrder\Models\PurchaseOrder;
use App\Modules\PurchaseOrder\Models\PurchaseOrderItem;

use App\Modules\GoodsReceiptNote\Services\GoodsReceiptNoteService;

class GoodsReceiptNoteServiceTest extends TestCase
{
    use RefreshDatabase;
    use CreatesTenant;
    use CreatesProduct;
    use CreatesWarehouse;

    protected Tenant $tenant;

    protected Product $product;

    protected Warehouse $warehouse;

    protected Supplier $supplier;

    protected Unit $baseUnit;

    protected Unit $purchaseUnit;

    protected ProductUnit $baseProductUnit;

    protected ProductUnit $purchaseProductUnit;

    protected ProductUnitService $productUnitService;

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

        $this->warehouse = $this->createWarehouse();

        $this->product = $this->createProduct();

        $this->supplier = Supplier::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Test Supplier',
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

        $this->productUnitService = app(
            ProductUnitService::class
        );

        $this->baseProductUnit =
            $this->productUnitService->syncBaseUnit(
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

        $this->service = app(
            GoodsReceiptNoteService::class
        );
    }

    public function test_creates_grn_item_with_valid_product_variant(): void
    {
        $variant = $this->createVariant(
            product: $this->product,
            sku: 'LAP-001',
            code: 'VAR-001'
        );

        $purchaseOrderItem = $this->createPurchaseOrderItem(
            product: $this->product,
            unitId: $this->purchaseUnit->id,
            variant: $variant
        );

        $grn = $this->service->create(
            $this->grnPayload(
                $purchaseOrderItem,
                [
                    'product_variant_id' => $variant->id,
                    'quantity_received' => 2,
                    'unit_cost' => 500,
                ]
            )
        );

        $item = $grn->items->first();

        $this->assertNotNull($item);

        $this->assertSame(
            (int) $variant->id,
            (int) $item->product_variant_id
        );

        $this->assertSame(
            (int) $this->product->id,
            (int) $item->product_id
        );

        $this->assertEquals(
            20,
            (float) $item->base_quantity
        );
    }

    public function test_persists_product_variant_id_on_grn_item(): void
    {
        $variant = $this->createVariant(
            product: $this->product,
            sku: 'LAP-002',
            code: 'VAR-002'
        );

        $purchaseOrderItem = $this->createPurchaseOrderItem(
            product: $this->product,
            unitId: $this->purchaseUnit->id,
            variant: $variant
        );

        $grn = $this->service->create(
            $this->grnPayload(
                $purchaseOrderItem,
                [
                    'product_variant_id' => $variant->id,
                    'quantity_received' => 3,
                    'unit_cost' => 750,
                ]
            )
        );

        $item = $grn->items->first();

        $this->assertNotNull($item);

        $this->assertDatabaseHas(
            'goods_receipt_note_items',
            [
                'id' => $item->id,
                'product_id' => $this->product->id,
                'product_variant_id' => $variant->id,
            ]
        );
    }

    public function test_rejects_product_variant_belonging_to_another_product(): void
    {
        $anotherProduct = $this->createProduct();

        $variant = $this->createVariant(
            product: $anotherProduct,
            sku: 'OTHER-001',
            code: 'OTHER-VAR-001'
        );

        $purchaseOrderItem = $this->createPurchaseOrderItem(
            product: $this->product,
            unitId: $this->purchaseUnit->id
        );

        $this->expectException(BusinessException::class);

        $this->service->create(
            $this->grnPayload(
                $purchaseOrderItem,
                [
                    'product_variant_id' => $variant->id,
                    'quantity_received' => 2,
                    'unit_cost' => 500,
                ]
            )
        );
    }

    public function test_rejects_inactive_product_variant(): void
    {
        $variant = $this->createVariant(
            product: $this->product,
            sku: 'LAP-003',
            code: 'VAR-003',
            isActive: false
        );

        $purchaseOrderItem = $this->createPurchaseOrderItem(
            product: $this->product,
            unitId: $this->purchaseUnit->id,
            variant: $variant
        );

        $this->expectException(BusinessException::class);

        $this->service->create(
            $this->grnPayload(
                $purchaseOrderItem,
                [
                    'product_variant_id' => $variant->id,
                    'quantity_received' => 2,
                    'unit_cost' => 500,
                ]
            )
        );
    }

    public function test_calculates_base_quantity_from_product_unit_conversion(): void
    {
        $purchaseOrderItem = $this->createPurchaseOrderItem(
            product: $this->product,
            unitId: $this->purchaseUnit->id
        );

        $grn = $this->service->create(
            $this->grnPayload(
                $purchaseOrderItem,
                [
                    'quantity_received' => 3,
                    'unit_cost' => 500,
                ]
            )
        );

        $item = $grn->items->first();

        $this->assertNotNull($item);

        $this->assertEquals(
            3,
            (float) $item->quantity_received
        );

        $this->assertEquals(
            30,
            (float) $item->base_quantity
        );
    }

    public function test_does_not_require_product_variant_for_normal_product(): void
    {
        $purchaseOrderItem = $this->createPurchaseOrderItem(
            product: $this->product,
            unitId: $this->purchaseUnit->id
        );

        $grn = $this->service->create(
            $this->grnPayload(
                $purchaseOrderItem,
                [
                    'quantity_received' => 2,
                    'unit_cost' => 1000,
                ]
            )
        );

        $item = $grn->items->first();

        $this->assertNotNull($item);

        $this->assertNull($item->product_variant_id);

        $this->assertEquals(
            20,
            (float) $item->base_quantity
        );
    }

    protected function createVariant(
        Product $product,
        string $sku,
        string $code,
        bool $isActive = true
    ): ProductVariant {
        return ProductVariant::create([
            'tenant_id' => $this->tenant->id,
            'product_id' => $product->id,
            'sku' => $sku,
            'code' => $code,
            'name' => $sku,
            'purchase_price' => 500,
            'selling_price' => 750,
            'is_active' => $isActive,
        ]);
    }

    protected function createPurchaseOrderItem(
        Product $product,
        int $unitId,
        ?ProductVariant $variant = null
    ): PurchaseOrderItem {
        $purchaseOrder = PurchaseOrder::create([
            'tenant_id' => $this->tenant->id,
            'supplier_id' => $this->supplier->id,
            'warehouse_id' => $this->warehouse->id,
            'order_number' => 'PO-TEST-' . uniqid(),
            'order_date' => now()->toDateString(),
            'status' => 'draft',
        ]);

        return PurchaseOrderItem::create([
            'tenant_id' => $this->tenant->id,
            'purchase_order_id' => $purchaseOrder->id,
            'product_id' => $product->id,
            'product_variant_id' => $variant?->id,
            'warehouse_id' => $this->warehouse->id,
            'line_type' => 'product',
            'item_name' => $product->name,
            'unit_of_measure_id' => $unitId,
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
    }

    protected function grnPayload(
        PurchaseOrderItem $purchaseOrderItem,
        array $itemOverrides
    ): array {
        return [
            'grn_date' => now()->toDateString(),
            'purchase_order_id' =>
                $purchaseOrderItem->purchase_order_id,
            'supplier_id' => $this->supplier->id,
            'warehouse_id' => $this->warehouse->id,
            'items' => [
                array_merge(
                    [
                        'purchase_order_item_id' =>
                            $purchaseOrderItem->id,
                        'product_id' =>
                            $purchaseOrderItem->product_id,
                        'unit_id' =>
                            $purchaseOrderItem->unit_of_measure_id,
                    ],
                    $itemOverrides
                ),
            ],
        ];
    }
}
