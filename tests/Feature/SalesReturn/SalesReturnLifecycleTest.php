<?php

namespace Tests\Feature\SalesReturn;

use App\Core\Tenant\TenantManager;
use App\Modules\Accounting\Enums\JournalVoucherTypeEnum;
use App\Modules\Accounting\Models\JournalEntry;
use App\Modules\Accounting\Services\AccountingSetupService;
use App\Modules\Customer\Models\Customer;
use App\Modules\Inventory\Models\ProductStock;
use App\Modules\Product\Models\Product;
use App\Modules\Purchase\Services\PurchaseService;
use App\Modules\Sales\Models\Sale;
use App\Modules\Sales\Services\SaleService;
use App\Modules\SalesReturn\Models\SalesReturn;
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

class SalesReturnLifecycleTest extends TestCase
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
        $this->product = $this->createProduct(['purchase_price' => 50, 'selling_price' => 100]);

        $supplier = Supplier::create(['tenant_id' => $this->tenant->id, 'name' => 'Supplier', 'is_active' => true]);
        $purchaseSvc = app(PurchaseService::class);
        $purchase = $purchaseSvc->create([
            'supplier_uuid' => $supplier->uuid,
            'warehouse_uuid' => $this->warehouse->uuid,
            'purchase_date' => now()->toDateString(),
            'items' => [['product_uuid' => $this->product->uuid, 'quantity' => 10, 'unit_cost' => 50]],
        ]);
        $purchaseSvc->confirm($purchase);

        $saleSvc = app(SaleService::class);
        $this->sale = $saleSvc->create([
            'customer_uuid' => $this->customer->uuid,
            'warehouse_uuid' => $this->warehouse->uuid,
            'sale_date' => now()->toDateString(),
            'items' => [['product_uuid' => $this->product->uuid, 'quantity' => 5, 'unit_price' => 100]],
        ]);
        $this->sale = $saleSvc->confirm($this->sale->uuid);
    }

    public function test_sales_return_can_be_confirmed_restoring_stock_and_posting_gl(): void
    {
        $saleItem = $this->sale->items->first();
        $createRes = $this->postJson(route('sales-returns.store'), [
            'sale_uuid' => $this->sale->uuid,
            'return_date' => now()->toDateString(),
            'items' => [['sale_item_uuid' => $saleItem->uuid, 'returned_quantity' => 2]],
        ]);

        $createRes
            ->assertCreated()
            ->assertJsonPath('data.items.0.unit_price', 100)
            ->assertJsonPath('data.items.0.unit_cost', 50)
            ->assertJsonPath('data.items.0.base_quantity', 2);
        $uuid = $createRes->json('data.uuid');

        $confirmRes = $this->postJson(route('sales-returns.confirm', ['uuid' => $uuid]));
        $confirmRes->assertOk();
        $this->assertContains($confirmRes->json('data.status'), ['approved', 'confirmed']);

        app(TenantManager::class)->setTenant($this->tenant);
        $salesReturn = SalesReturn::query()->where('uuid', $uuid)->first();
        $this->assertNotNull($salesReturn);

        $stock = ProductStock::query()->where('product_id', $this->product->id)->where('warehouse_id', $this->warehouse->id)->first();
        $this->assertEquals(7, (float) $stock->quantity);

        $this->assertDatabaseHas('stock_ledgers', [
            'product_id' => $this->product->id,
            'warehouse_id' => $this->warehouse->id,
            'quantity_in' => 2,
            'referenceable_id' => $salesReturn->id,
        ]);

        $journalEntry = JournalEntry::query()
            ->where('voucher_type', JournalVoucherTypeEnum::SALES_RETURN->value)
            ->where('reference_id', $salesReturn->id)
            ->first();
        $this->assertNotNull($journalEntry);

        $refreshedSale = Sale::query()->find($this->sale->id);
        $this->assertEquals(300, (float) $refreshedSale->due_amount);
    }

    public function test_cannot_confirm_already_confirmed_sales_return(): void
    {
        $saleItem = $this->sale->items->first();
        $createRes = $this->postJson(route('sales-returns.store'), [
            'sale_uuid' => $this->sale->uuid,
            'return_date' => now()->toDateString(),
            'items' => [['sale_item_uuid' => $saleItem->uuid, 'returned_quantity' => 1]],
        ]);

        $uuid = $createRes->json('data.uuid');
        $this->postJson(route('sales-returns.confirm', ['uuid' => $uuid]))->assertOk();
        $this->postJson(route('sales-returns.confirm', ['uuid' => $uuid]))->assertUnprocessable();
    }

    public function test_cannot_return_more_than_sold_quantity(): void
    {
        $saleItem = $this->sale->items->first();
        $res = $this->postJson(route('sales-returns.store'), [
            'sale_uuid' => $this->sale->uuid,
            'return_date' => now()->toDateString(),
            'items' => [['sale_item_uuid' => $saleItem->uuid, 'returned_quantity' => 6]],
        ]);
        $res->assertUnprocessable();
    }

    public function test_sales_return_can_be_cancelled(): void
    {
        $saleItem = $this->sale->items->first();
        $createRes = $this->postJson(route('sales-returns.store'), [
            'sale_uuid' => $this->sale->uuid,
            'return_date' => now()->toDateString(),
            'items' => [['sale_item_uuid' => $saleItem->uuid, 'returned_quantity' => 1]],
        ]);

        $uuid = $createRes->json('data.uuid');
        $this->postJson(route('sales-returns.confirm', ['uuid' => $uuid]))->assertOk();

        $cancelRes = $this->postJson(route('sales-returns.cancel', ['uuid' => $uuid]), ['reason' => 'Customer change']);
        $cancelRes->assertOk()->assertJsonPath('data.status', 'cancelled');

        app(TenantManager::class)->setTenant($this->tenant);
        $stock = ProductStock::query()->where('product_id', $this->product->id)->where('warehouse_id', $this->warehouse->id)->first();
        $this->assertNotNull($stock);
        $this->assertEquals(5, (float) $stock->quantity);
    }

    public function test_cannot_modify_confirmed_sales_return(): void
    {
        $saleItem = $this->sale->items->first();
        $createRes = $this->postJson(route('sales-returns.store'), [
            'sale_uuid' => $this->sale->uuid,
            'return_date' => now()->toDateString(),
            'items' => [['sale_item_uuid' => $saleItem->uuid, 'returned_quantity' => 1]],
        ]);

        $uuid = $createRes->json('data.uuid');
        $this->postJson(route('sales-returns.confirm', ['uuid' => $uuid]))->assertOk();

        $updateRes = $this->putJson(route('sales-returns.update', ['uuid' => $uuid]), [
            'items' => [['sale_item_uuid' => $saleItem->uuid, 'returned_quantity' => 2]],
        ]);
        $updateRes->assertUnprocessable();
    }
}
