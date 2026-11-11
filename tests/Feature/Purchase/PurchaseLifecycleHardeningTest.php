<?php

namespace Tests\Feature\Purchase;

use App\Core\Tenant\TenantManager;
use App\Modules\Accounting\Services\AccountingSetupService;
use App\Modules\Inventory\Models\ProductStock;
use App\Modules\Purchase\Models\Purchase;
use App\Modules\Product\Enums\InventoryTrackingTypeEnum;
use App\Modules\Supplier\Models\Supplier;
use App\Modules\Tenant\Models\Tenant;
use App\Modules\User\Models\User;
use App\Modules\Warehouse\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Support\CreatesProduct;
use Tests\Support\CreatesSupplier;
use Tests\Support\CreatesTenant;
use Tests\Support\CreatesUser;
use Tests\Support\CreatesWarehouse;
use Tests\TestCase;

class PurchaseLifecycleHardeningTest extends TestCase
{
    use RefreshDatabase, CreatesTenant, CreatesUser, CreatesSupplier, CreatesWarehouse, CreatesProduct;

    protected Tenant $tenant;
    protected User $user;
    protected Supplier $supplier;
    protected Warehouse $warehouse;
    protected $product;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = $this->createTestTenant();
        $this->user = $this->createTestUser();
        Sanctum::actingAs($this->user);

        app(TenantManager::class)->setTenant($this->tenant);
        app(AccountingSetupService::class)->setup($this->tenant);

        $this->supplier = $this->createSupplier();
        $this->warehouse = $this->createWarehouse();
        $this->product = $this->createProduct([
            'purchase_price' => 50,
            'selling_price' => 100,
        ]);
    }

    public function test_confirming_a_purchase_twice_does_not_post_inventory_twice(): void
    {
        $purchaseUuid = $this->createPurchase();

        $this->postJson(route('purchases.confirm', $purchaseUuid))
            ->assertOk()
            ->assertJsonPath('data.status', 'confirmed');

        $this->postJson(route('purchases.confirm', $purchaseUuid))
            ->assertStatus(422);

        app(TenantManager::class)->setTenant($this->tenant);

        $stock = ProductStock::query()
            ->where('product_id', $this->product->id)
            ->where('warehouse_id', $this->warehouse->id)
            ->firstOrFail();

        $this->assertSame(10.0, (float) $stock->quantity);

        $this->assertSame(
            1,
            Purchase::query()
                ->where('uuid', $purchaseUuid)
                ->firstOrFail()
                ->journalEntry()
                ->count()
        );
    }

    public function test_confirmed_purchase_cannot_be_cancelled_without_reversal(): void
    {
        $purchaseUuid = $this->createPurchase();

        $this->postJson(route('purchases.confirm', $purchaseUuid))
            ->assertOk();

        $this->postJson(route('purchases.cancel', $purchaseUuid), [
            'reason' => 'Attempted cancellation after stock posting',
        ])->assertStatus(422);

        app(TenantManager::class)->setTenant($this->tenant);

        $purchase = Purchase::query()
            ->where('uuid', $purchaseUuid)
            ->firstOrFail();

        $this->assertSame('confirmed', $purchase->status->value);

        $stock = ProductStock::query()
            ->where('product_id', $this->product->id)
            ->where('warehouse_id', $this->warehouse->id)
            ->firstOrFail();

        $this->assertSame(10.0, (float) $stock->quantity);
    }

    public function test_purchase_item_percentage_discount_cannot_exceed_one_hundred_percent(): void
    {
        $response = $this->postJson(route('purchases.store'), [
            'supplier_id' => $this->supplier->id,
            'warehouse_id' => $this->warehouse->id,
            'purchase_date' => now()->toDateString(),
            'items' => [[
                'product_id' => $this->product->id,
                'quantity' => 1,
                'unit_cost' => 100,
                'discount_type' => 'percentage',
                'discount_rate' => 101,
            ]],
        ]);

        $response->assertStatus(422);
    }

    public function test_purchase_header_discount_cannot_exceed_net_item_subtotal(): void
    {
        $response = $this->postJson(route('purchases.store'), [
            'supplier_id' => $this->supplier->id,
            'warehouse_id' => $this->warehouse->id,
            'purchase_date' => now()->toDateString(),
            'discount_amount' => 101,
            'items' => [[
                'product_id' => $this->product->id,
                'quantity' => 1,
                'unit_cost' => 100,
            ]],
        ]);

        $response->assertStatus(422);
    }

    public function test_purchase_create_rejects_forged_nested_serial_identifiers(): void
    {
        $serialProduct = $this->createProduct([
            'inventory_tracking_type' => InventoryTrackingTypeEnum::SERIAL,
        ]);

        $response = $this->postJson(route('purchases.store'), [
            'supplier_id' => $this->supplier->id,
            'warehouse_id' => $this->warehouse->id,
            'purchase_date' => now()->toDateString(),
            'items' => [[
                'product_id' => $serialProduct->id,
                'quantity' => 1,
                'unit_cost' => 50,
                'serials' => [[
                    'serial_number' => 'PURCHASE-SERIAL-001',
                    'product_serial_id' => 999,
                ]],
            ]],
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors('items.0.serials.0');
    }

    public function test_purchase_update_rejects_forged_nested_serial_identifiers(): void
    {
        $serialProduct = $this->createProduct([
            'inventory_tracking_type' => InventoryTrackingTypeEnum::SERIAL,
        ]);

        $createResponse = $this->postJson(route('purchases.store'), [
            'supplier_id' => $this->supplier->id,
            'warehouse_id' => $this->warehouse->id,
            'purchase_date' => now()->toDateString(),
            'items' => [[
                'product_id' => $serialProduct->id,
                'quantity' => 1,
                'unit_cost' => 50,
                'serials' => ['PURCHASE-SERIAL-002'],
            ]],
        ]);
        $createResponse->assertCreated();

        $purchaseUuid = $createResponse->json('data.uuid');

        $this->putJson(route('purchases.update', ['purchase' => $purchaseUuid]), [
            'items' => [[
                'product_id' => $serialProduct->id,
                'quantity' => 1,
                'unit_cost' => 50,
                'serials' => [[
                    'serial_number' => 'PURCHASE-SERIAL-002',
                    'product_serial_id' => 999,
                ]],
            ]],
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('items.0.serials.0');
    }

    protected function createPurchase(): string
    {
        $response = $this->postJson(route('purchases.store'), [
            'supplier_id' => $this->supplier->id,
            'warehouse_id' => $this->warehouse->id,
            'purchase_date' => now()->toDateString(),
            'items' => [[
                'product_id' => $this->product->id,
                'quantity' => 10,
                'unit_cost' => 50,
            ]],
        ]);

        $response->assertCreated();

        return $response->json('data.uuid');
    }
}
