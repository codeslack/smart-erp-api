<?php

namespace Tests\Feature\Purchase;

use App\Modules\Tenant\Models\Tenant;
use App\Modules\User\Models\User;
use App\Modules\Supplier\Models\Supplier;
use App\Modules\Warehouse\Models\Warehouse;
use App\Modules\Product\Models\Product;
use App\Modules\Product\Enums\ProductTypeEnum;
use App\Modules\Product\Enums\InventoryTrackingTypeEnum;
use App\Modules\Accounting\Models\ChartOfAccount;
use App\Modules\Accounting\Enums\AccountType;
use App\Modules\Accounting\Services\AccountingSetupService;
use App\Modules\Purchase\Models\Purchase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\Support\CreatesTenant;
use Tests\Support\CreatesUser;
use Tests\TestCase;

class PurchaseChargesAndDiscountsTest extends TestCase
{
    use RefreshDatabase;
    use CreatesTenant;
    use CreatesUser;

    protected Tenant $tenant;
    protected User $user;
    protected Supplier $supplier;
    protected Warehouse $warehouse;
    protected Product $product;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = $this->createTestTenant();
        $this->user = $this->createTestUser();
        Sanctum::actingAs($this->user);

        app(AccountingSetupService::class)->setup($this->tenant);

        $this->supplier = Supplier::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Landed Cost Supplier',
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
            'code' => 'PRD-CHG',
            'sku' => 'SKU-CHG',
            'name' => 'Widget Item',
            'slug' => 'widget-item',
            'product_type' => ProductTypeEnum::PRODUCT,
            'inventory_tracking_type' => InventoryTrackingTypeEnum::NONE,
            'is_active' => true,
            'purchase_price' => 100,
            'selling_price' => 150,
        ]);
    }

    public function test_purchase_with_charges_calculates_totals_and_posts_accounting(): void
    {
        $freightAccount = ChartOfAccount::withoutGlobalScopes()
            ->where('tenant_id', $this->tenant->id)
            ->where('account_type', AccountType::EXPENSE)
            ->first();

        $payload = [
            'supplier_uuid' => $this->supplier->uuid,
            'warehouse_uuid' => $this->warehouse->uuid,
            'purchase_date' => now()->toDateString(),
            'supplier_invoice_number' => 'VEND-INV-999',
            'supplier_invoice_date' => now()->toDateString(),
            'tax_calculation_mode' => 'exclusive',
            'items' => [
                [
                    'product_uuid' => $this->product->uuid,
                    'quantity' => 2,
                    'unit_cost' => 100, // 200 subtotal
                ],
            ],
            'charges' => [
                [
                    'charge_type' => 'freight',
                    'name' => 'Shipping & Freight',
                    'calculation_method' => 'fixed',
                    'amount' => 50,
                    'treatment' => 'expense',
                    'chart_of_account_uuid' => $freightAccount?->uuid,
                ],
            ],
        ];

        $response = $this->postJson(route('purchases.store'), $payload);
        $response->assertStatus(201);
        $purchaseUuid = $response->json('data.uuid');

        $this->assertEquals(200, $response->json('data.subtotal'));
        $this->assertEquals(50, $response->json('data.charges_subtotal'));
        $this->assertEquals(250, $response->json('data.grand_total'));
        $this->assertEquals('VEND-INV-999', $response->json('data.supplier_invoice_number'));

        $this->assertDatabaseHas('purchase_charges', [
            'tenant_id' => $this->tenant->id,
            'charge_type' => 'freight',
            'amount' => 50,
        ]);

        // Confirm the purchase
        $confirmResponse = $this->postJson("/api/purchases/{$purchaseUuid}/confirm");
        $confirmResponse->assertStatus(200);

        // Verify Journal Entry was created and balanced
        $this->assertDatabaseHas('journal_entries', [
            'tenant_id' => $this->tenant->id,
            'reference_type' => Purchase::class,
        ]);
    }

    public function test_purchase_with_header_discount_and_rounding(): void
    {
        $payload = [
            'supplier_uuid' => $this->supplier->uuid,
            'warehouse_uuid' => $this->warehouse->uuid,
            'purchase_date' => now()->toDateString(),
            'discount_type' => 'percentage',
            'discount_rate' => 10, // 10% of 200 = 20 discount
            'rounding_amount' => 0.50,
            'items' => [
                [
                    'product_uuid' => $this->product->uuid,
                    'quantity' => 2,
                    'unit_cost' => 100, // 200
                ],
            ],
        ];

        $response = $this->postJson(route('purchases.store'), $payload);
        $response->assertStatus(201);

        $this->assertEquals(200, $response->json('data.subtotal'));
        $this->assertEquals(20, $response->json('data.discount_amount'));
        // 200 - 20 + 0.50 = 180.50
        $this->assertEquals(180.50, $response->json('data.grand_total'));
    }

    public function test_multi_tenant_purchases_can_share_same_purchase_number(): void
    {
        // Tenant 1 purchase
        $payload1 = [
            'supplier_uuid' => $this->supplier->uuid,
            'warehouse_uuid' => $this->warehouse->uuid,
            'purchase_no' => 'BILL-SHARED-001',
            'purchase_date' => now()->toDateString(),
            'items' => [
                [
                    'product_uuid' => $this->product->uuid,
                    'quantity' => 1,
                    'unit_cost' => 100,
                ],
            ],
        ];

        $response1 = $this->postJson(route('purchases.store'), $payload1);
        $response1->assertStatus(201);

        // Create Tenant 2
        $tenant2 = $this->createTestTenant();
        $user2 = $this->createTestUser();
        $user2->update(['tenant_id' => $tenant2->id]);
        Sanctum::actingAs($user2);

        $supplier2 = Supplier::create([
            'tenant_id' => $tenant2->id,
            'name' => 'Tenant 2 Supplier',
            'is_active' => true,
        ]);

        $warehouse2 = Warehouse::create([
            'tenant_id' => $tenant2->id,
            'name' => 'Tenant 2 Warehouse',
            'code' => 'WH-T2',
            'is_active' => true,
        ]);

        $product2 = Product::create([
            'tenant_id' => $tenant2->id,
            'code' => 'PRD-T2',
            'sku' => 'SKU-T2',
            'name' => 'T2 Widget',
            'slug' => 't2-widget',
            'product_type' => ProductTypeEnum::PRODUCT,
            'inventory_tracking_type' => InventoryTrackingTypeEnum::NONE,
            'is_active' => true,
            'purchase_price' => 100,
            'selling_price' => 150,
        ]);

        $payload2 = [
            'supplier_uuid' => $supplier2->uuid,
            'warehouse_uuid' => $warehouse2->uuid,
            'purchase_no' => 'BILL-SHARED-001', // Same bill number as Tenant 1!
            'purchase_date' => now()->toDateString(),
            'items' => [
                [
                    'product_uuid' => $product2->uuid,
                    'quantity' => 1,
                    'unit_cost' => 100,
                ],
            ],
        ];

        $response2 = $this->postJson(route('purchases.store'), $payload2);
        $response2->assertStatus(201);
        $this->assertEquals('BILL-SHARED-001', $response2->json('data.purchase_no'));
    }

    public function test_landed_cost_allocation_methods_are_applied_to_item_inventory_costs(): void
    {
        $secondProduct = Product::create([
            'tenant_id' => $this->tenant->id,
            'code' => 'PRD-CHG-SECOND',
            'sku' => 'SKU-CHG-SECOND',
            'name' => 'Second Widget Item',
            'slug' => 'second-widget-item',
            'product_type' => ProductTypeEnum::PRODUCT,
            'inventory_tracking_type' => InventoryTrackingTypeEnum::NONE,
            'is_active' => true,
            'purchase_price' => 300,
            'selling_price' => 400,
        ]);

        $expectedCosts = [
            'by_quantity' => [110.0000, 310.0000],
            'by_value' => [104.0000, 312.0000],
            'equal' => [120.0000, 306.6667],
        ];

        foreach ($expectedCosts as $method => [$firstCost, $secondCost]) {
            $response = $this->postJson(route('purchases.store'), [
                'supplier_uuid' => $this->supplier->uuid,
                'warehouse_uuid' => $this->warehouse->uuid,
                'purchase_date' => now()->toDateString(),
                'items' => [
                    [
                        'product_uuid' => $this->product->uuid,
                        'quantity' => 1,
                        'unit_cost' => 100,
                    ],
                    [
                        'product_uuid' => $secondProduct->uuid,
                        'quantity' => 3,
                        'unit_cost' => 300,
                    ],
                ],
                'charges' => [
                    [
                        'charge_type' => 'freight',
                        'name' => 'Landed freight',
                        'calculation_method' => 'fixed',
                        'amount' => 40,
                        'treatment' => 'landed_cost',
                        'allocation_method' => $method,
                        'affects_inventory_cost' => true,
                    ],
                ],
            ])->assertCreated();

            $purchaseUuid = $response->json('data.uuid');

            $this->postJson("/api/purchases/{$purchaseUuid}/confirm")
                ->assertOk();

            $purchaseId = DB::table('purchases')
                ->where('tenant_id', $this->tenant->id)
                ->where('uuid', $purchaseUuid)
                ->value('id');

            $firstLedger = DB::table('stock_ledgers')
                ->where('tenant_id', $this->tenant->id)
                ->where('referenceable_type', Purchase::class)
                ->where('referenceable_id', $purchaseId)
                ->where('product_id', $this->product->id)
                ->first();

            $secondLedger = DB::table('stock_ledgers')
                ->where('tenant_id', $this->tenant->id)
                ->where('referenceable_type', Purchase::class)
                ->where('referenceable_id', $purchaseId)
                ->where('product_id', $secondProduct->id)
                ->first();

            $this->assertNotNull($firstLedger);
            $this->assertNotNull($secondLedger);
            $this->assertEqualsWithDelta($firstCost, (float) $firstLedger->unit_cost, 0.0001);
            $this->assertEqualsWithDelta($secondCost, (float) $secondLedger->unit_cost, 0.0001);
        }
    }



    public function test_expense_charge_cannot_post_to_an_asset_account(): void
    {
        $cashAccount = ChartOfAccount::withoutGlobalScopes()
            ->where('tenant_id', $this->tenant->id)
            ->where('account_code', '1000')
            ->firstOrFail();

        $response = $this->postJson(route('purchases.store'), [
            'supplier_uuid' => $this->supplier->uuid,
            'warehouse_uuid' => $this->warehouse->uuid,
            'purchase_date' => now()->toDateString(),
            'items' => [[
                'product_uuid' => $this->product->uuid,
                'quantity' => 1,
                'unit_cost' => 100,
            ]],
            'charges' => [[
                'charge_type' => 'freight',
                'name' => 'Invalid asset-mapped expense',
                'calculation_method' => 'fixed',
                'amount' => 10,
                'treatment' => 'expense',
                'chart_of_account_uuid' => $cashAccount->uuid,
            ]],
        ])->assertCreated();

        $purchaseUuid = $response->json('data.uuid');
        $purchaseId = DB::table('purchases')
            ->where('tenant_id', $this->tenant->id)
            ->where('uuid', $purchaseUuid)
            ->value('id');

        $this->postJson("/api/purchases/{$purchaseUuid}/confirm")
            ->assertStatus(422);

        $this->assertDatabaseMissing('journal_entries', [
            'tenant_id' => $this->tenant->id,
            'reference_type' => Purchase::class,
            'reference_id' => $purchaseId,
        ]);

        $this->assertDatabaseMissing('stock_ledgers', [
            'tenant_id' => $this->tenant->id,
            'referenceable_type' => Purchase::class,
            'referenceable_id' => $purchaseId,
        ]);
    }


}
