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
}
