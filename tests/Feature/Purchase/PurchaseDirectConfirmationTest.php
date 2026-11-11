<?php

namespace Tests\Feature\Purchase;

use App\Modules\Tenant\Models\Tenant;
use App\Modules\User\Models\User;
use App\Modules\Supplier\Models\Supplier;
use App\Modules\Warehouse\Models\Warehouse;
use App\Modules\Product\Models\Product;
use App\Modules\Product\Models\ProductVariant;
use App\Modules\ProductUnit\Models\ProductUnit;
use App\Modules\Unit\Models\Unit;
use App\Modules\Product\Enums\ProductTypeEnum;
use App\Modules\Product\Enums\InventoryTrackingTypeEnum;
use App\Modules\Purchase\Models\Purchase;
use App\Modules\Payment\Models\Payment;
use App\Modules\Payment\Enums\PaymentTypeEnum;
use App\Modules\Payment\Enums\PaymentPartyTypeEnum;
use App\Modules\Payment\Enums\PaymentPurposeEnum;
use App\Modules\Payment\Enums\PaymentMethodEnum;
use App\Modules\Payment\Enums\PaymentStatusEnum;
use App\Modules\Accounting\Models\ChartOfAccount;
use App\Modules\Accounting\Services\AccountingSetupService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Support\CreatesTenant;
use Tests\Support\CreatesUser;
use Tests\TestCase;

class PurchaseDirectConfirmationTest extends TestCase
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

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = $this->createTestTenant();
        $this->user = $this->createTestUser();
        Sanctum::actingAs($this->user);

        app(AccountingSetupService::class)->setup($this->tenant);

        $this->supplier = Supplier::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Acme Supplier',
            'is_active' => true,
        ]);

        $this->warehouse = Warehouse::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Main Warehouse',
            'code' => 'WH-MAIN',
            'is_active' => true,
        ]);

        $this->product = Product::create([
            'tenant_id' => $this->tenant->id,
            'code' => 'PRD-001',
            'sku' => 'SKU-001',
            'name' => 'Standard Widget',
            'slug' => 'standard-widget',
            'product_type' => ProductTypeEnum::PRODUCT,
            'inventory_tracking_type' => InventoryTrackingTypeEnum::NONE,
            'has_variants' => true,
            'is_active' => true,
            'purchase_price' => 100,
            'selling_price' => 150,
        ]);

        $this->variant = ProductVariant::create([
            'tenant_id' => $this->tenant->id,
            'product_id' => $this->product->id,
            'sku' => 'SKU-001-RED',
            'code' => 'VAR-RED',
            'name' => 'Red Widget',
            'purchase_price' => 110,
            'selling_price' => 160,
            'is_active' => true,
        ]);
    }

    public function test_direct_purchase_can_be_confirmed_and_posts_stock_and_accounting(): void
    {
        $payload = [
            'supplier_uuid' => $this->supplier->uuid,
            'warehouse_uuid' => $this->warehouse->uuid,
            'purchase_date' => now()->toDateString(),
            'items' => [
                [
                    'product_uuid' => $this->product->uuid,
                    'product_variant_uuid' => $this->variant->uuid,
                    'quantity' => 2,
                    'unit_cost' => 150,
                ],
            ],
        ];

        $createResponse = $this->postJson(route('purchases.store'), $payload);
        $createResponse->assertCreated();
        $uuid = $createResponse->json('data.uuid');

        $response = $this->postJson("/api/purchases/{$uuid}/confirm");

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.status', 'confirmed');

        $this->assertDatabaseHas('purchases', [
            'uuid' => $uuid,
            'status' => 'confirmed',
        ]);

        // Verify Journal Entry was created
        $this->assertDatabaseHas('journal_entries', [
            'tenant_id' => $this->tenant->id,
            'reference_type' => Purchase::class,
        ]);

        // Verify Stock Ledger was created for variant with quantity_in
        $this->assertDatabaseHas('stock_ledgers', [
            'tenant_id' => $this->tenant->id,
            'product_id' => $this->product->id,
            'product_variant_id' => $this->variant->id,
            'quantity_in' => 2,
        ]);
    }

    public function test_purchase_unit_conversion_uses_derived_base_quantity_and_cost(): void
    {
        $baseUnit = Unit::create([
            'tenant_id' => $this->tenant->id,
            'code' => 'PCS',
            'name' => 'Piece',
            'short_name' => 'pc',
            'is_active' => true,
        ]);

        $boxUnit = Unit::create([
            'tenant_id' => $this->tenant->id,
            'code' => 'BOX',
            'name' => 'Box',
            'short_name' => 'box',
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

        $payload = [
            'supplier_uuid' => $this->supplier->uuid,
            'warehouse_uuid' => $this->warehouse->uuid,
            'purchase_date' => now()->toDateString(),
            'items' => [
                [
                    'product_uuid' => $this->product->uuid,
                    'unit_uuid' => $boxUnit->uuid,
                    'quantity' => 2,
                    'unit_cost' => 500,
                ],
            ],
        ];

        $createResponse = $this->postJson(route('purchases.store'), $payload);

        $createResponse
            ->assertCreated()
            ->assertJsonPath('data.items.0.base_quantity', 20)
            ->assertJsonPath('data.items.0.base_unit_cost', 50);

        $purchaseUuid = $createResponse->json('data.uuid');
        $purchaseId = Purchase::query()
            ->where('uuid', $purchaseUuid)
            ->value('id');

        $this->assertDatabaseHas('purchase_items', [
            'purchase_id' => $purchaseId,
            'quantity' => 2,
            'base_quantity' => 20,
            'unit_cost' => 500,
            'base_unit_cost' => 50,
        ]);

        $this->postJson("/api/purchases/{$purchaseUuid}/confirm")
            ->assertOk();

        $this->assertDatabaseHas('stock_ledgers', [
            'product_id' => $this->product->id,
            'quantity_in' => 20,
            'unit_cost' => 50,
        ]);
    }

    public function test_client_cannot_override_purchase_base_quantity(): void
    {
        $payload = [
            'supplier_uuid' => $this->supplier->uuid,
            'warehouse_uuid' => $this->warehouse->uuid,
            'purchase_date' => now()->toDateString(),
            'items' => [
                [
                    'product_uuid' => $this->product->uuid,
                    'quantity' => 2,
                    'unit_cost' => 100,
                    'base_quantity' => 500,
                ],
            ],
        ];

        $this->postJson(route('purchases.store'), $payload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors('items.0');
    }

    public function test_service_product_does_not_post_stock(): void
    {
        $serviceProduct = Product::create([
            'tenant_id' => $this->tenant->id,
            'code' => 'SRV-001',
            'sku' => 'SKU-SRV',
            'name' => 'Consulting Service',
            'slug' => 'consulting-service',
            'product_type' => ProductTypeEnum::SERVICE,
            'inventory_tracking_type' => InventoryTrackingTypeEnum::NONE,
            'has_variants' => false,
            'is_active' => true,
            'purchase_price' => 500,
            'selling_price' => 1000,
        ]);

        $payload = [
            'supplier_uuid' => $this->supplier->uuid,
            'purchase_date' => now()->toDateString(),
            'items' => [
                [
                    'product_uuid' => $serviceProduct->uuid,
                    'quantity' => 1,
                    'unit_cost' => 500,
                ],
            ],
        ];

        $createResponse = $this->postJson(route('purchases.store'), $payload);
        $createResponse->assertCreated();
        $uuid = $createResponse->json('data.uuid');

        $response = $this->postJson("/api/purchases/{$uuid}/confirm");
        $response->assertOk();

        // Verify NO Stock Ledger was created for service product
        $this->assertDatabaseMissing('stock_ledgers', [
            'tenant_id' => $this->tenant->id,
            'product_id' => $serviceProduct->id,
        ]);
    }

    public function test_supplier_advance_is_auto_adjusted_on_confirmation(): void
    {
        $cashAccount = ChartOfAccount::where('tenant_id', $this->tenant->id)
            ->where('account_code', '1010')
            ->firstOrFail();

        // Create confirmed supplier advance payment of 100
        Payment::create([
            'tenant_id' => $this->tenant->id,
            'payment_no' => 'PAY-ADV-001',
            'payment_type' => PaymentTypeEnum::PAYMENT,
            'purpose' => PaymentPurposeEnum::SUPPLIER_ADVANCE,
            'payment_date' => now()->toDateString(),
            'party_type' => PaymentPartyTypeEnum::SUPPLIER,
            'party_id' => $this->supplier->id,
            'account_id' => $cashAccount->id,
            'amount' => 100,
            'payment_method' => PaymentMethodEnum::CASH,
            'status' => PaymentStatusEnum::CONFIRMED->value,
        ]);

        // Create purchase bill of 300
        $payload = [
            'supplier_uuid' => $this->supplier->uuid,
            'warehouse_uuid' => $this->warehouse->uuid,
            'purchase_date' => now()->toDateString(),
            'items' => [
                [
                    'product_uuid' => $this->product->uuid,
                    'product_variant_uuid' => $this->variant->uuid,
                    'quantity' => 2,
                    'unit_cost' => 150,
                ],
            ],
        ];

        $createResponse = $this->postJson(route('purchases.store'), $payload);
        $createResponse->assertCreated();
        $uuid = $createResponse->json('data.uuid');

        // Confirm Purchase Bill -> Should auto-adjust 100 from advance
        $response = $this->postJson("/api/purchases/{$uuid}/confirm");

        $response->assertOk()
            ->assertJsonPath('data.grand_total', 300)
            ->assertJsonPath('data.paid_amount', 100)
            ->assertJsonPath('data.due_amount', 200);

        $this->assertDatabaseHas('purchases', [
            'uuid' => $uuid,
            'paid_amount' => 100,
            'due_amount' => 200,
        ]);
    }
}
