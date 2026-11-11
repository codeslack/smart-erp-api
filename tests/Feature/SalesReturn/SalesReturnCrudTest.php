<?php

namespace Tests\Feature\SalesReturn;

use App\Core\Tenant\TenantManager;
use App\Modules\Accounting\Services\AccountingSetupService;
use App\Modules\Customer\Models\Customer;
use App\Modules\Product\Models\Product;
use App\Modules\Purchase\Services\PurchaseService;
use App\Modules\Sales\Models\Sale;
use App\Modules\Sales\Services\SaleService;
use App\Modules\SalesReturn\Enums\SalesReturnStatusEnum;
use App\Modules\Supplier\Models\Supplier;
use App\Modules\Tenant\Models\Tenant;
use App\Modules\User\Models\User;
use App\Modules\Warehouse\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Support\CreatesCustomer;
use Tests\Support\CreatesProduct;
use Tests\Support\CreatesTenant;
use Tests\Support\CreatesUser;
use Tests\Support\CreatesWarehouse;
use Tests\TestCase;

class SalesReturnCrudTest extends TestCase
{
    use RefreshDatabase, CreatesTenant, CreatesUser, CreatesCustomer, CreatesWarehouse, CreatesProduct;

    protected Tenant $tenant;
    protected User $user;
    protected Customer $customer;
    protected Warehouse $warehouse;
    protected Product $product;
    protected Sale $sale;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tenant = $this->createTestTenant();
        $this->user = $this->createTestUser();
        Sanctum::actingAs($this->user);
        app(TenantManager::class)->setTenant($this->tenant);
        app(AccountingSetupService::class)->setup($this->tenant);

        $this->customer = $this->createCustomer();
        $this->warehouse = $this->createWarehouse();
        $this->product = $this->createProduct(['purchase_price' => 60, 'selling_price' => 100]);

        $supplier = Supplier::create(['tenant_id' => $this->tenant->id, 'name' => 'Supplier', 'is_active' => true]);
        $purchaseSvc = app(PurchaseService::class);
        $purchase = $purchaseSvc->create([
            'supplier_uuid' => $supplier->uuid,
            'warehouse_uuid' => $this->warehouse->uuid,
            'purchase_date' => now()->toDateString(),
            'items' => [['product_uuid' => $this->product->uuid, 'quantity' => 20, 'unit_cost' => 60]],
        ]);
        $purchaseSvc->confirm($purchase);

        $saleSvc = app(SaleService::class);
        $this->sale = $saleSvc->create([
            'customer_uuid' => $this->customer->uuid,
            'warehouse_uuid' => $this->warehouse->uuid,
            'sale_date' => now()->toDateString(),
            'items' => [['product_uuid' => $this->product->uuid, 'quantity' => 10, 'unit_price' => 100]],
        ]);
        $this->sale = $saleSvc->confirm($this->sale->uuid);
    }

    protected function makeReturn(int $quantity = 2): string
    {
        $saleItem = $this->sale->items->first();
        $res = $this->postJson(route('sales-returns.store'), [
            'sale_uuid' => $this->sale->uuid,
            'return_date' => now()->toDateString(),
            'return_reason' => 'Customer returned item',
            'items' => [['sale_item_uuid' => $saleItem->uuid, 'product_uuid' => $this->product->uuid, 'returned_quantity' => $quantity, 'unit_price' => 100]],
        ]);
        return $res->json('data.uuid');
    }

    public function test_sales_return_can_be_created(): void
    {
        $saleItem = $this->sale->items->first();
        $res = $this->postJson(route('sales-returns.store'), [
            'sale_uuid' => $this->sale->uuid,
            'return_date' => now()->toDateString(),
            'return_reason' => 'Customer returned item',
            'items' => [['sale_item_uuid' => $saleItem->uuid, 'product_uuid' => $this->product->uuid, 'returned_quantity' => 2, 'unit_price' => 100]],
        ]);

        $res->assertCreated()->assertJsonStructure([
            'success', 'message', 'data' => ['uuid', 'return_no', 'status', 'customer' => ['uuid', 'name'], 'warehouse' => ['uuid', 'name'], 'grand_total', 'items'],
        ]);

        $this->assertDatabaseHas('sales_returns', [
            'tenant_id' => $this->tenant->id,
            'sale_id' => $this->sale->id,
            'status' => SalesReturnStatusEnum::DRAFT->value,
            'grand_total' => 200.0000,
        ]);
    }

    public function test_sales_return_exposes_only_uuid_not_internal_id(): void
    {
        $uuid = $this->makeReturn(1);
        $res = $this->getJson(route('sales-returns.show', ['uuid' => $uuid]));
        $data = $res->json('data');

        $this->assertArrayNotHasKey('id', $data);
        $this->assertArrayHasKey('uuid', $data);
        if (! empty($data['items'])) {
            $this->assertArrayNotHasKey('id', $data['items'][0]);
            $this->assertArrayHasKey('uuid', $data['items'][0]);
        }
    }

    public function test_sales_return_can_be_retrieved_by_uuid(): void
    {
        $uuid = $this->makeReturn(3);
        $res = $this->getJson(route('sales-returns.show', ['uuid' => $uuid]));
        $res->assertOk()->assertJsonPath('data.uuid', $uuid)->assertJsonPath('data.grand_total', 300);
    }

    public function test_sales_return_can_be_updated_in_draft_state(): void
    {
        $uuid = $this->makeReturn(2);
        $saleItem = $this->sale->items->first();
        $updateRes = $this->putJson(route('sales-returns.update', ['uuid' => $uuid]), [
            'return_reason' => 'Updated defect reason',
            'items' => [['sale_item_uuid' => $saleItem->uuid, 'returned_quantity' => 4, 'unit_price' => 100]],
        ]);
        $updateRes->assertOk()->assertJsonPath('data.grand_total', 400);
    }

    public function test_sales_return_rejects_unvalidated_base_quantity_on_create(): void
    {
        $saleItem = $this->sale->items->first();

        $response = $this->postJson(route('sales-returns.store'), [
            'sale_uuid' => $this->sale->uuid,
            'return_date' => now()->toDateString(),
            'items' => [[
                'sale_item_uuid' => $saleItem->uuid,
                'returned_quantity' => 1,
                'unit_price' => 100,
                'base_quantity' => 500,
            ]],
        ]);

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors('items.0');
    }

    public function test_sales_return_rejects_unvalidated_base_quantity_on_update(): void
    {
        $uuid = $this->makeReturn(1);
        $saleItem = $this->sale->items->first();

        $response = $this->putJson(route('sales-returns.update', ['uuid' => $uuid]), [
            'items' => [[
                'sale_item_uuid' => $saleItem->uuid,
                'returned_quantity' => 2,
                'unit_price' => 100,
                'base_quantity' => 500,
            ]],
        ]);

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors('items.0');
    }

    public function test_sales_return_uses_original_sale_price_and_cost(): void
    {
        $saleItem = $this->sale->items->first();

        $response = $this->postJson(route('sales-returns.store'), [
            'sale_uuid' => $this->sale->uuid,
            'return_date' => now()->toDateString(),
            'items' => [[
                'sale_item_uuid' => $saleItem->uuid,
                'returned_quantity' => 2,
                'unit_price' => 999,
                'unit_cost' => 999,
            ]],
        ]);

        $response
            ->assertCreated()
            ->assertJsonPath('data.grand_total', 200);

        $this->assertDatabaseHas('sales_return_items', [
            'sale_item_id' => $saleItem->id,
            'returned_quantity' => 2,
            'unit_price' => 100,
            'unit_cost' => $saleItem->cost_price,
        ]);
    }

    public function test_sales_return_items_can_be_retrieved(): void
    {
        $uuid = $this->makeReturn(2);
        $res = $this->getJson(route('sales-returns.items', ['uuid' => $uuid]));
        $res->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.quantity', 2);
    }

    public function test_sales_return_can_be_deleted_in_draft_state(): void
    {
        $uuid = $this->makeReturn(1);
        $this->deleteJson(route('sales-returns.destroy', ['uuid' => $uuid]))->assertOk();
        $this->assertSoftDeleted('sales_returns', ['uuid' => $uuid]);
    }

    public function test_tenant_isolation_prevents_cross_tenant_access(): void
    {
        $uuid = $this->makeReturn(1);
        $otherTenant = $this->createTestTenant();
        $otherUser = $this->createTestUser(['tenant_id' => $otherTenant->id]);
        Sanctum::actingAs($otherUser);
        app(TenantManager::class)->setTenant($otherTenant);

        $this->getJson(route('sales-returns.show', ['uuid' => $uuid]))->assertNotFound();
    }
}
