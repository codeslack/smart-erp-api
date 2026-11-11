<?php

namespace Tests\Feature\Sales;

use App\Core\Tenant\TenantManager;
use App\Modules\Accounting\Enums\JournalVoucherTypeEnum;
use App\Modules\Accounting\Services\AccountingSetupService;
use App\Modules\Customer\Models\Customer;
use App\Modules\Inventory\Enums\InventoryTransactionTypeEnum;
use App\Modules\Inventory\Models\ProductStock;
use App\Modules\Product\Models\Product;
use App\Modules\ProductUnit\Models\ProductUnit;
use App\Modules\Unit\Models\Unit;
use App\Modules\Purchase\Services\PurchaseService;
use App\Modules\Sales\Models\Sale;
use App\Modules\SalesOrder\Models\SalesOrder;
use App\Modules\SalesOrder\Models\SalesOrderItem;
use App\Modules\Supplier\Models\Supplier;
use App\Modules\Tenant\Models\Tenant;
use App\Modules\User\Models\User;
use App\Modules\Warehouse\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Support\CreatesCustomer;
use Tests\Support\CreatesProduct;
use Tests\Support\CreatesSupplier;
use Tests\Support\CreatesTenant;
use Tests\Support\CreatesUser;
use Tests\Support\CreatesWarehouse;
use Tests\TestCase;

class SaleLifecycleTest extends TestCase
{
    use RefreshDatabase, CreatesTenant, CreatesUser, CreatesCustomer, CreatesSupplier, CreatesWarehouse, CreatesProduct;

    protected Tenant $tenant;
    protected User $user;
    protected Customer $customer;
    protected Supplier $supplier;
    protected Warehouse $warehouse;
    protected Product $product;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = $this->createTestTenant();
        $this->user = $this->createTestUser();
        Sanctum::actingAs($this->user);

        app(TenantManager::class)->setTenant($this->tenant);
        app(AccountingSetupService::class)->setup($this->tenant);

        $this->customer = $this->createCustomer();
        $this->supplier = $this->createSupplier();
        $this->warehouse = $this->createWarehouse();
        $this->product = $this->createProduct(['purchase_price' => 50, 'selling_price' => 100]);

        $purchaseSvc = app(PurchaseService::class);
        $purchase = $purchaseSvc->create([
            'supplier_uuid' => $this->supplier->uuid,
            'warehouse_uuid' => $this->warehouse->uuid,
            'purchase_date' => now()->toDateString(),
            'items' => [
                ['product_uuid' => $this->product->uuid, 'quantity' => 10, 'unit_cost' => 50],
            ],
        ]);
        $purchaseSvc->confirm($purchase);
    }

    public function test_sale_can_be_confirmed_and_reduces_stock_and_posts_gl(): void
    {
        $createRes = $this->postJson(route('sales.store'), [
            'customer_id' => $this->customer->id,
            'warehouse_id' => $this->warehouse->id,
            'sale_date' => now()->toDateString(),
            'items' => [
                ['product_id' => $this->product->id, 'quantity' => 3, 'unit_price' => 100],
            ],
        ]);

        $uuid = $createRes->json('data.uuid');
        $confirmRes = $this->postJson(route('sales.confirm', $uuid));

        $confirmRes->assertOk()->assertJsonPath('data.status', 'confirmed');

        app(TenantManager::class)->setTenant($this->tenant);
        $sale = Sale::query()->where('uuid', $uuid)->first();
        $this->assertNotNull($sale);

        $stock = ProductStock::query()
            ->where('product_id', $this->product->id)
            ->where('warehouse_id', $this->warehouse->id)
            ->first();
        $this->assertEquals(7, (float) $stock->quantity);

        $this->assertDatabaseHas('stock_ledgers', [
            'product_id' => $this->product->id,
            'warehouse_id' => $this->warehouse->id,
            'quantity_out' => 3,
            'transaction_type' => InventoryTransactionTypeEnum::SALE->value,
            'referenceable_id' => $sale->id,
        ]);

        $this->assertDatabaseHas('journal_entries', [
            'voucher_type' => JournalVoucherTypeEnum::SALE->value,
            'reference_id' => $sale->id,
        ]);
    }

    public function test_sale_rejects_conflicting_customer_id_and_uuid(): void
    {
        $otherCustomer = $this->createCustomer();

        $response = $this->postJson(route('sales.store'), [
            'customer_id' => $this->customer->id,
            'customer_uuid' => $otherCustomer->uuid,
            'warehouse_id' => $this->warehouse->id,
            'sale_date' => now()->toDateString(),
            'items' => [[
                'product_id' => $this->product->id,
                'quantity' => 1,
                'unit_price' => 100,
            ]],
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors('customer_uuid');
    }

    public function test_sale_rejects_conflicting_product_id_and_uuid(): void
    {
        $otherProduct = $this->createProduct([
            'purchase_price' => 20,
            'selling_price' => 40,
        ]);

        $response = $this->postJson(route('sales.store'), [
            'customer_id' => $this->customer->id,
            'warehouse_id' => $this->warehouse->id,
            'sale_date' => now()->toDateString(),
            'items' => [[
                'product_id' => $this->product->id,
                'product_uuid' => $otherProduct->uuid,
                'quantity' => 1,
                'unit_price' => 100,
            ]],
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors('items.0.product_uuid');
    }

    public function test_percentage_item_discount_cannot_exceed_one_hundred_percent(): void
    {
        $response = $this->postJson(route('sales.store'), [
            'customer_id' => $this->customer->id,
            'warehouse_id' => $this->warehouse->id,
            'sale_date' => now()->toDateString(),
            'items' => [[
                'product_id' => $this->product->id,
                'quantity' => 1,
                'unit_price' => 100,
                'discount_type' => 'percentage',
                'discount_rate' => 101,
            ]],
        ]);

        $response->assertStatus(422);
    }

    public function test_header_discount_cannot_exceed_net_item_subtotal(): void
    {
        $response = $this->postJson(route('sales.store'), [
            'customer_id' => $this->customer->id,
            'warehouse_id' => $this->warehouse->id,
            'sale_date' => now()->toDateString(),
            'discount_amount' => 101,
            'items' => [[
                'product_id' => $this->product->id,
                'quantity' => 1,
                'unit_price' => 100,
            ]],
        ]);

        $response->assertStatus(422);
    }

    public function test_sale_unit_conversion_posts_stock_in_base_units(): void
    {
        $baseUnit = Unit::create([
            'tenant_id' => $this->tenant->id,
            'code' => 'SPCS',
            'name' => 'Sale Piece',
            'short_name' => 'spc',
            'is_active' => true,
        ]);

        $boxUnit = Unit::create([
            'tenant_id' => $this->tenant->id,
            'code' => 'SBOX',
            'name' => 'Sale Box',
            'short_name' => 'sbox',
            'is_active' => true,
        ]);

        $this->product->update([
            'unit_id' => $baseUnit->id,
        ]);

        ProductUnit::create([
            'product_id' => $this->product->id,
            'unit_id' => $baseUnit->id,
            'conversion_factor' => 1,
            'is_purchase_unit' => true,
            'is_sales_unit' => true,
            'is_active' => true,
        ]);

        ProductUnit::create([
            'product_id' => $this->product->id,
            'unit_id' => $boxUnit->id,
            'conversion_factor' => 10,
            'is_purchase_unit' => true,
            'is_sales_unit' => true,
            'is_active' => true,
        ]);

        $createResponse = $this->postJson(route('sales.store'), [
            'customer_id' => $this->customer->id,
            'warehouse_id' => $this->warehouse->id,
            'sale_date' => now()->toDateString(),
            'items' => [
                [
                    'product_id' => $this->product->id,
                    'unit_id' => $boxUnit->id,
                    'quantity' => 1,
                    'unit_price' => 1000,
                ],
            ],
        ]);

        $createResponse
            ->assertCreated()
            ->assertJsonPath('data.items.0.base_quantity', 10);

        $saleUuid = $createResponse->json('data.uuid');

        $this->postJson(route('sales.confirm', $saleUuid))
            ->assertOk();

        app(TenantManager::class)->setTenant($this->tenant);

        $stock = ProductStock::query()
            ->where('product_id', $this->product->id)
            ->where('warehouse_id', $this->warehouse->id)
            ->firstOrFail();

        $this->assertSame(0.0, (float) $stock->quantity);

        $this->assertDatabaseHas('stock_ledgers', [
            'product_id' => $this->product->id,
            'warehouse_id' => $this->warehouse->id,
            'quantity_out' => 10,
            'transaction_type' => InventoryTransactionTypeEnum::SALE->value,
        ]);
    }

    public function test_confirmed_sale_cannot_be_updated(): void
    {
        $createRes = $this->postJson(route('sales.store'), [
            'customer_id' => $this->customer->id,
            'warehouse_id' => $this->warehouse->id,
            'sale_date' => now()->toDateString(),
            'items' => [
                ['product_id' => $this->product->id, 'quantity' => 1, 'unit_price' => 100],
            ],
        ]);

        $uuid = $createRes->json('data.uuid');
        $this->postJson(route('sales.confirm', $uuid))->assertOk();

        $updateRes = $this->putJson(route('sales.update', $uuid), [
            'notes' => 'Attempting update on confirmed sale',
        ]);

        $updateRes->assertStatus(422)->assertJsonValidationErrors(['status']);
    }

    public function test_sale_can_be_cancelled(): void
    {
        $createRes = $this->postJson(route('sales.store'), [
            'customer_id' => $this->customer->id,
            'warehouse_id' => $this->warehouse->id,
            'sale_date' => now()->toDateString(),
            'items' => [
                ['product_id' => $this->product->id, 'quantity' => 2, 'unit_price' => 100],
            ],
        ]);

        $uuid = $createRes->json('data.uuid');
        $cancelRes = $this->postJson(route('sales.cancel', $uuid), [
            'reason' => 'Customer cancelled order',
        ]);

        $cancelRes->assertOk()
            ->assertJsonPath('data.status', 'cancelled')
            ->assertJsonPath('data.cancellation_reason', 'Customer cancelled order');

        $this->assertDatabaseHas('sales', [
            'tenant_id' => $this->tenant->id,
            'uuid' => $uuid,
            'status' => 'cancelled',
            'cancellation_reason' => 'Customer cancelled order',
        ]);
    }

    public function test_sales_can_be_paginated_and_filtered(): void
    {
        $this->postJson(route('sales.store'), [
            'customer_id' => $this->customer->id,
            'warehouse_id' => $this->warehouse->id,
            'sale_date' => '2026-10-20',
            'items' => [['product_id' => $this->product->id, 'quantity' => 1, 'unit_price' => 50]],
        ])->assertCreated();

        $this->postJson(route('sales.store'), [
            'customer_id' => $this->customer->id,
            'warehouse_id' => $this->warehouse->id,
            'sale_date' => '2026-10-25',
            'items' => [['product_id' => $this->product->id, 'quantity' => 2, 'unit_price' => 50]],
        ])->assertCreated();

        $res = $this->getJson(route('sales.index', ['customer_uuid' => $this->customer->uuid]));
        $res->assertOk()->assertJsonStructure(['data' => [['uuid', 'sale_no', 'status']]]);
        $this->assertCount(2, $res->json('data'));
    }

    public function test_sale_tenant_isolation(): void
    {
        $createRes = $this->postJson(route('sales.store'), [
            'customer_id' => $this->customer->id,
            'warehouse_id' => $this->warehouse->id,
            'sale_date' => now()->toDateString(),
            'items' => [['product_id' => $this->product->id, 'quantity' => 1, 'unit_price' => 50]],
        ]);

        $uuid = $createRes->json('data.uuid');

        $secondTenant = $this->createTestTenant();
        $secondUser = $this->createTestUser(['tenant_id' => $secondTenant->id]);
        Sanctum::actingAs($secondUser);
        app(TenantManager::class)->setTenant($secondTenant);

        $this->getJson(route('sales.show', $uuid))->assertNotFound();
    }

    protected function createConfirmedSalesOrder(float $quantity): SalesOrderItem
    {
        $response = $this->postJson(route('sales-orders.store'), [
            'customer_id' => $this->customer->id,
            'warehouse_id' => $this->warehouse->id,
            'order_date' => now()->toDateString(),
            'items' => [[
                'product_id' => $this->product->id,
                'quantity' => $quantity,
                'unit_price' => 100,
            ]],
        ]);

        $response->assertCreated();
        $orderUuid = $response->json('data.uuid');

        $this->postJson(route('sales-orders.confirm', $orderUuid))
            ->assertOk();

        app(TenantManager::class)->setTenant($this->tenant);

        $order = SalesOrder::query()
            ->where('uuid', $orderUuid)
            ->firstOrFail();

        return $order->items()->firstOrFail();
    }

    public function test_sale_invoice_updates_linked_sales_order_item_quantity(): void
    {
        $orderItem = $this->createConfirmedSalesOrder(5);

        $createResponse = $this->postJson(route('sales.store'), [
            'customer_id' => $this->customer->id,
            'warehouse_id' => $this->warehouse->id,
            'sales_order_uuid' => $orderItem->salesOrder->uuid,
            'sale_date' => now()->toDateString(),
            'items' => [[
                'product_id' => $this->product->id,
                'sales_order_item_uuid' => $orderItem->uuid,
                'quantity' => 3,
                'unit_price' => 100,
            ]],
        ]);

        $createResponse->assertCreated()
            ->assertJsonPath('data.items.0.sales_order_item_uuid', $orderItem->uuid);

        $saleUuid = $createResponse->json('data.uuid');

        $this->postJson(route('sales.confirm', $saleUuid))
            ->assertOk()
            ->assertJsonPath('data.status', 'confirmed');

        $this->assertDatabaseHas('sales_order_items', [
            'id' => $orderItem->id,
            'invoiced_quantity' => 3,
        ]);
    }

    public function test_sale_confirmation_rejects_over_invoicing_a_sales_order_item(): void
    {
        $orderItem = $this->createConfirmedSalesOrder(5);

        $firstSale = $this->postJson(route('sales.store'), [
            'customer_id' => $this->customer->id,
            'warehouse_id' => $this->warehouse->id,
            'sales_order_uuid' => $orderItem->salesOrder->uuid,
            'sale_date' => now()->toDateString(),
            'items' => [[
                'product_id' => $this->product->id,
                'sales_order_item_uuid' => $orderItem->uuid,
                'quantity' => 4,
                'unit_price' => 100,
            ]],
        ])->assertCreated();

        $this->postJson(route('sales.confirm', $firstSale->json('data.uuid')))
            ->assertOk();

        $secondSale = $this->postJson(route('sales.store'), [
            'customer_id' => $this->customer->id,
            'warehouse_id' => $this->warehouse->id,
            'sales_order_uuid' => $orderItem->salesOrder->uuid,
            'sale_date' => now()->toDateString(),
            'items' => [[
                'product_id' => $this->product->id,
                'sales_order_item_uuid' => $orderItem->uuid,
                'quantity' => 2,
                'unit_price' => 100,
            ]],
        ])->assertCreated();

        $this->postJson(route('sales.confirm', $secondSale->json('data.uuid')))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('items');

        $this->assertDatabaseHas('sales_order_items', [
            'id' => $orderItem->id,
            'invoiced_quantity' => 4,
        ]);
    }

    public function test_cancelling_confirmed_sale_reverses_sales_order_invoiced_quantity(): void
    {
        $orderItem = $this->createConfirmedSalesOrder(5);

        $saleResponse = $this->postJson(route('sales.store'), [
            'customer_id' => $this->customer->id,
            'warehouse_id' => $this->warehouse->id,
            'sales_order_uuid' => $orderItem->salesOrder->uuid,
            'sale_date' => now()->toDateString(),
            'items' => [[
                'product_id' => $this->product->id,
                'sales_order_item_uuid' => $orderItem->uuid,
                'quantity' => 3,
                'unit_price' => 100,
            ]],
        ])->assertCreated();

        $saleUuid = $saleResponse->json('data.uuid');

        $this->postJson(route('sales.confirm', $saleUuid))->assertOk();
        $this->assertDatabaseHas('sales_order_items', [
            'id' => $orderItem->id,
            'invoiced_quantity' => 3,
        ]);

        $this->postJson(route('sales.cancel', $saleUuid), [
            'reason' => 'Invoice cancelled',
        ])->assertOk();

        $this->assertDatabaseHas('sales_order_items', [
            'id' => $orderItem->id,
            'invoiced_quantity' => 0,
        ]);
    }

    public function test_sale_rejects_sales_order_item_from_another_order(): void
    {
        $selectedOrderItem = $this->createConfirmedSalesOrder(5);
        $otherOrderItem = $this->createConfirmedSalesOrder(5);

        $response = $this->postJson(route('sales.store'), [
            'customer_id' => $this->customer->id,
            'warehouse_id' => $this->warehouse->id,
            'sales_order_uuid' => $selectedOrderItem->salesOrder->uuid,
            'sale_date' => now()->toDateString(),
            'items' => [[
                'product_id' => $this->product->id,
                'sales_order_item_uuid' => $otherOrderItem->uuid,
                'quantity' => 1,
                'unit_price' => 100,
            ]],
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors('items.0.sales_order_item_uuid');
    }

    public function test_sale_rejects_sales_order_item_with_mismatched_product(): void
    {
        $orderItem = $this->createConfirmedSalesOrder(5);
        $otherProduct = $this->createProduct([
            'purchase_price' => 20,
            'selling_price' => 40,
        ]);

        $response = $this->postJson(route('sales.store'), [
            'customer_id' => $this->customer->id,
            'warehouse_id' => $this->warehouse->id,
            'sales_order_uuid' => $orderItem->salesOrder->uuid,
            'sale_date' => now()->toDateString(),
            'items' => [[
                'product_id' => $otherProduct->id,
                'sales_order_item_uuid' => $orderItem->uuid,
                'quantity' => 1,
                'unit_price' => 40,
            ]],
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors('items.0.sales_order_item_uuid');
    }


    public function test_sales_order_cannot_be_cancelled_after_linked_invoice_confirmation(): void
    {
        $orderItem = $this->createConfirmedSalesOrder(5);

        $saleResponse = $this->postJson(route('sales.store'), [
            'customer_id' => $this->customer->id,
            'warehouse_id' => $this->warehouse->id,
            'sales_order_uuid' => $orderItem->salesOrder->uuid,
            'sale_date' => now()->toDateString(),
            'items' => [[
                'product_id' => $this->product->id,
                'sales_order_item_uuid' => $orderItem->uuid,
                'quantity' => 2,
                'unit_price' => 100,
            ]],
        ])->assertCreated();

        $this->postJson(route('sales.confirm', $saleResponse->json('data.uuid')))
            ->assertOk();

        $this->postJson(route('sales-orders.cancel', $orderItem->salesOrder->uuid), [
            'reason' => 'Attempted cancellation after invoicing',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('status');

        $this->assertDatabaseHas('sales_orders', [
            'id' => $orderItem->sales_order_id,
            'status' => 'confirmed',
        ]);
    }


    public function test_sale_confirmation_rejects_customer_mismatch_with_sales_order(): void
    {
        $orderItem = $this->createConfirmedSalesOrder(5);
        $otherCustomer = $this->createCustomer();

        $saleResponse = $this->postJson(route('sales.store'), [
            'customer_id' => $otherCustomer->id,
            'warehouse_id' => $this->warehouse->id,
            'sales_order_uuid' => $orderItem->salesOrder->uuid,
            'sale_date' => now()->toDateString(),
            'items' => [[
                'product_id' => $this->product->id,
                'sales_order_item_uuid' => $orderItem->uuid,
                'quantity' => 1,
                'unit_price' => 100,
            ]],
        ])->assertCreated();

        $this->postJson(route('sales.confirm', $saleResponse->json('data.uuid')))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('customer_id');

        $this->assertDatabaseHas('sales_order_items', [
            'id' => $orderItem->id,
            'invoiced_quantity' => 0,
        ]);
    }

}
