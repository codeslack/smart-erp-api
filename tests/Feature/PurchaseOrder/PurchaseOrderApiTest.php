<?php

namespace Tests\Feature\PurchaseOrder;

use App\Core\Tenant\TenantManager;
use App\Modules\Tenant\Models\Tenant;
use App\Modules\User\Models\User;
use App\Modules\Supplier\Models\Supplier;
use App\Modules\Warehouse\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Support\CreatesTenant;
use Tests\Support\CreatesUser;
use Tests\TestCase;

use App\Modules\Product\Enums\InventoryTrackingTypeEnum;
use App\Modules\Product\Enums\ProductTypeEnum;
use App\Modules\Product\Models\Product;
use App\Modules\Product\Models\ProductVariant;
use App\Modules\PurchaseOrder\Models\PurchaseOrder;

class PurchaseOrderApiTest extends TestCase
{
    use RefreshDatabase;
    use CreatesTenant;
    use CreatesUser;

    protected Tenant $tenant;

    protected User $user;

    protected Supplier $supplier;

    protected Warehouse $warehouse;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = $this->createTestTenant();

        $this->user = $this->createTestUser();

        Sanctum::actingAs($this->user);

        $this->supplier = $this->createSupplier();

        $this->warehouse = $this->createWarehouse();
    }

    public function test_purchase_order_can_be_created(): void
    {
        $response = $this->postJson(
            route('purchase-orders.store'),
            $this->validPurchaseOrder()
        );

        $response
            ->assertCreated()
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'uuid',
                    'order_number',
                    'status',
                    'supplier',
                    'warehouse',
                    'items',
                ],
            ]);

        $this->assertDatabaseHas(
            'purchase_orders',
            [
                'tenant_id' => $this->tenant->id,
                'status' => 'draft',
            ]
        );
    }

    public function test_purchase_order_accepts_valid_product_variant(): void
    {
        $product = $this->createVariantProduct();

        $variant = $this->createProductVariant(
            $product
        );

        $payload = $this->validPurchaseOrder();

        $payload['items'] = [
            [
                'line_type' => 'product',
                'item_name' => 'Laptop Variant',
                'product_id' => $product->id,
                'product_variant_id' => $variant->id,
                'quantity' => 2,
                'unit_price' => 50000,
                'discount_type' => 'fixed',
                'discount_rate' => 0,
                'is_tax_inclusive' => false,
            ],
        ];

        $response = $this->postJson(
            route('purchase-orders.store'),
            $payload
        );

        $response->assertCreated();

        app(\App\Core\Tenant\TenantManager::class)
            ->setTenant($this->tenant);

        $purchaseOrderId =
            PurchaseOrder::query()
            ->where(
                'uuid',
                $response->json('data.uuid')
            )
            ->value('id');

        $this->assertNotNull($purchaseOrderId);

        $this->assertDatabaseHas(
            'purchase_order_items',
            [
                'purchase_order_id' => $purchaseOrderId,
                'product_id' => $product->id,
                'product_variant_id' => $variant->id,
            ]
        );
    }

    public function test_purchase_order_rejects_variant_belonging_to_another_product(): void
    {
        $productA = $this->createVariantProduct(
            'Laptop A'
        );

        $productB = $this->createVariantProduct(
            'Laptop B'
        );

        $variantB = $this->createProductVariant(
            $productB
        );

        $payload = $this->validPurchaseOrder();

        $payload['items'] = [
            [
                'line_type' => 'product',
                'item_name' => 'Invalid Variant',
                'product_id' => $productA->id,
                'product_variant_id' => $variantB->id,
                'quantity' => 1,
                'unit_price' => 50000,
                'discount_type' => 'fixed',
                'discount_rate' => 0,
                'is_tax_inclusive' => false,
            ],
        ];

        $response = $this->postJson(
            route('purchase-orders.store'),
            $payload
        );

        $response
            ->assertStatus(422)
            ->assertJsonPath(
                'code',
                'PURCHASE_ORDER_INVALID_PRODUCT_VARIANT'
            )
            ->assertJsonPath(
                'message',
                'The selected product variant does not belong to the selected product.'
            );
    }

    public function test_purchase_order_rejects_variant_without_product(): void
    {
        $product = $this->createVariantProduct();

        $variant = $this->createProductVariant(
            $product
        );

        $payload = $this->validPurchaseOrder();

        $payload['items'] = [
            [
                'line_type' => 'product',
                'item_name' => 'Variant Without Product',
                'product_id' => null,
                'product_variant_id' => $variant->id,
                'quantity' => 1,
                'unit_price' => 50000,
                'discount_type' => 'fixed',
                'discount_rate' => 0,
                'is_tax_inclusive' => false,
            ],
        ];

        $response = $this->postJson(
            route('purchase-orders.store'),
            $payload
        );

        $response
            ->assertStatus(422)
            ->assertJsonPath(
                'code',
                'PURCHASE_ORDER_PRODUCT_REQUIRED_FOR_VARIANT'
            )
            ->assertJsonPath(
                'message',
                'Product is required when a product variant is selected.'
            );
    }

    public function test_purchase_order_calculates_and_persists_header_totals(): void
    {
        $payload = $this->validPurchaseOrder();

        $payload['items'] = [
            [
                'line_type' => 'service',
                'item_name' => 'Laptop Supply',
                'quantity' => 10,
                'unit_price' => 500,
                'discount_type' => 'fixed',
                'discount_rate' => 200,
                'is_tax_inclusive' => false,
            ],
            [
                'line_type' => 'service',
                'item_name' => 'Installation',
                'quantity' => 5,
                'unit_price' => 1000,
                'discount_type' => 'fixed',
                'discount_rate' => 500,
                'is_tax_inclusive' => false,
            ],
        ];

        $response = $this->postJson(
            route('purchase-orders.store'),
            $payload
        );

        $response->assertCreated();

        $uuid = $response->json('data.uuid');

        $this->assertNotEmpty($uuid);

        $response
            ->assertJsonPath(
                'data.amounts.subtotal',
                '9300.0000'
            )
            ->assertJsonPath(
                'data.amounts.discount',
                '700.0000'
            )
            ->assertJsonPath(
                'data.amounts.additional_charge',
                '0.0000'
            )
            ->assertJsonPath(
                'data.amounts.tax_total',
                '0.0000'
            )
            ->assertJsonPath(
                'data.amounts.rounding_adjustment',
                '0.0000'
            )
            ->assertJsonPath(
                'data.amounts.grand_total',
                '9300.0000'
            );

        $this->assertDatabaseHas(
            'purchase_orders',
            [
                'uuid' => $uuid,
                'subtotal_amount' => 9300,
                'discount_amount' => 700,
                'additional_charge_amount' => 0,
                'tax_total_amount' => 0,
                'rounding_adjustment' => 0,
                'grand_total_amount' => 9300,
            ]
        );
    }

    public function test_purchase_order_can_be_listed(): void
    {
        $this->postJson(
            route('purchase-orders.store'),
            $this->validPurchaseOrder()
        )->assertCreated();

        $response = $this->getJson(
            route('purchase-orders.index')
        );

        $response
            ->assertSuccessful()
            ->assertJsonStructure([
                'success',
                'message',
                'data',
            ]);
    }

    public function test_purchase_order_can_be_shown(): void
    {
        $createResponse = $this->postJson(
            route('purchase-orders.store'),
            $this->validPurchaseOrder()
        );

        $uuid = $createResponse->json(
            'data.uuid'
        );

        $response = $this->getJson(
            route(
                'purchase-orders.show',
                $uuid
            )
        );

        $response
            ->assertSuccessful()
            ->assertJsonPath(
                'data.uuid',
                $uuid
            );
    }

    public function test_purchase_order_can_be_updated_while_draft(): void
    {
        $createResponse = $this->postJson(
            route('purchase-orders.store'),
            $this->validPurchaseOrder()
        );

        $uuid = $createResponse->json(
            'data.uuid'
        );

        $payload = $this->validPurchaseOrder();

        $payload['items'][0]['unit_price'] = 7500;

        $response = $this->putJson(
            route(
                'purchase-orders.update',
                $uuid
            ),
            $payload
        );

        $response
            ->assertSuccessful()
            ->assertJsonPath(
                'data.uuid',
                $uuid
            )
            ->assertJsonPath(
                'data.items.0.unit_price',
                '7500.0000'
            );
    }

    public function test_purchase_order_can_be_confirmed(): void
    {
        $createResponse = $this->postJson(
            route('purchase-orders.store'),
            $this->validPurchaseOrder()
        );

        $uuid = $createResponse->json(
            'data.uuid'
        );

        $response = $this->postJson(
            route(
                'purchase-orders.confirm',
                $uuid
            )
        );

        $response
            ->assertSuccessful()
            ->assertJsonPath(
                'data.status',
                'confirmed'
            );
    }

    public function test_confirmed_purchase_order_cannot_be_updated(): void
    {
        $createResponse = $this->postJson(
            route('purchase-orders.store'),
            $this->validPurchaseOrder()
        );

        $uuid = $createResponse->json(
            'data.uuid'
        );

        $this->postJson(
            route(
                'purchase-orders.confirm',
                $uuid
            )
        )->assertSuccessful();

        $response = $this->putJson(
            route(
                'purchase-orders.update',
                $uuid
            ),
            $this->validPurchaseOrder()
        );

        $response->assertStatus(422);
    }

    public function test_purchase_order_can_be_cancelled(): void
    {
        $createResponse = $this->postJson(
            route('purchase-orders.store'),
            $this->validPurchaseOrder()
        );

        $uuid = $createResponse->json(
            'data.uuid'
        );

        $response = $this->postJson(
            route(
                'purchase-orders.cancel',
                $uuid
            ),
            [
                'reason' => 'Supplier changed.',
            ]
        );

        $response
            ->assertSuccessful()
            ->assertJsonPath(
                'data.status',
                'cancelled'
            );
    }

    public function test_draft_purchase_order_can_be_deleted(): void
    {
        $createResponse = $this->postJson(
            route('purchase-orders.store'),
            $this->validPurchaseOrder()
        );

        $uuid = $createResponse->json(
            'data.uuid'
        );

        $response = $this->deleteJson(
            route(
                'purchase-orders.destroy',
                $uuid
            )
        );

        $response->assertSuccessful();

        $this->assertSoftDeleted(
            'purchase_orders',
            [
                'uuid' => $uuid,
            ]
        );
    }

    public function test_confirmed_purchase_order_cannot_be_deleted(): void
    {
        $createResponse = $this->postJson(
            route('purchase-orders.store'),
            $this->validPurchaseOrder()
        );

        $uuid = $createResponse->json(
            'data.uuid'
        );

        $this->postJson(
            route(
                'purchase-orders.confirm',
                $uuid
            )
        )->assertSuccessful();

        $response = $this->deleteJson(
            route(
                'purchase-orders.destroy',
                $uuid
            )
        );

        $response->assertStatus(422);
    }

    public function test_purchase_order_items_can_be_returned(): void
    {
        $createResponse = $this->postJson(
            route('purchase-orders.store'),
            $this->validPurchaseOrder()
        );

        $uuid = $createResponse->json(
            'data.uuid'
        );

        $response = $this->getJson(
            route(
                'purchase-orders.items',
                $uuid
            )
        );

        $response
            ->assertSuccessful()
            ->assertJsonStructure([
                'success',
                'message',
                'data',
            ]);
    }

    public function test_purchase_order_is_tenant_scoped(): void
    {
        $createResponse = $this->postJson(
            route('purchase-orders.store'),
            $this->validPurchaseOrder()
        );

        $uuid = $createResponse->json(
            'data.uuid'
        );

        /*
        |--------------------------------------------------------------------------
        | Switch to another tenant
        |--------------------------------------------------------------------------
        */

        $otherTenant = $this->createTestTenant();

        app(TenantManager::class)->setTenant(
            $otherTenant
        );

        /*
        |--------------------------------------------------------------------------
        | Authenticate as a user belonging to Tenant B
        |--------------------------------------------------------------------------
        */

        $otherUser = $this->createTestUser();

        Sanctum::actingAs(
            $otherUser
        );

        /*
        |--------------------------------------------------------------------------
        | Tenant B must not see Tenant A's purchase order
        |--------------------------------------------------------------------------
        */

        $response = $this->getJson(
            route(
                'purchase-orders.show',
                $uuid
            )
        );

        $response->assertStatus(404);
    }

    private function validPurchaseOrder(): array
    {
        return [
            'order_date' => now()->toDateString(),
            'expected_delivery_date' => now()
                ->addDays(7)
                ->toDateString(),

            'supplier_id' => $this->supplier->id,
            'warehouse_id' => $this->warehouse->id,

            'currency_code' => 'INR',
            'exchange_rate' => 1,

            'notes' => 'Test purchase order.',
            'terms_and_conditions' => 'Standard terms.',

            'items' => [
                [
                    'line_type' => 'service',
                    'item_name' => 'Laptop Installation',
                    'quantity' => 1,
                    'unit_price' => 5000,
                    'discount_type' => 'fixed',
                    'discount_rate' => 0,
                    'is_tax_inclusive' => false,
                ],
            ],
        ];
    }

    private function createVariantProduct(
        string $name = 'Test Laptop'
    ): Product {
        return Product::create([
            'tenant_id' => $this->tenant->id,

            'code' => 'PRD-' . uniqid(),
            'sku' => 'SKU-' . uniqid(),
            'name' => $name,
            'slug' => strtolower(
                str_replace(
                    ' ',
                    '-',
                    $name
                )
            ) . '-' . uniqid(),

            'product_type' =>
            ProductTypeEnum::PRODUCT,

            'inventory_tracking_type' =>
            InventoryTrackingTypeEnum::NONE,

            'has_variants' => true,
            'is_active' => true,

            'purchase_price' => 40000,
            'selling_price' => 50000,
        ]);
    }

    private function createProductVariant(
        Product $product
    ): ProductVariant {
        return ProductVariant::create([
            'tenant_id' => $this->tenant->id,

            'product_id' => $product->id,

            'sku' => 'VAR-SKU-' . uniqid(),
            'code' => 'VAR-' . uniqid(),
            'name' => '16GB / 512GB',

            'purchase_price' => 45000,
            'selling_price' => 55000,

            'is_active' => true,
        ]);
    }

    private function createSupplier(): Supplier
    {
        return Supplier::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Test Supplier',
            'is_active' => true,
        ]);
    }

    private function createWarehouse(): Warehouse
    {
        return Warehouse::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Test Warehouse',
            'code' => 'WH-TEST',
            'is_active' => true,
        ]);
    }
}
