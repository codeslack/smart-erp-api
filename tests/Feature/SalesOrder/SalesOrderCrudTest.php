<?php

namespace Tests\Feature\SalesOrder;

use App\Core\Tenant\TenantManager;
use App\Modules\Customer\Models\Customer;
use App\Modules\Product\Models\Product;
use App\Modules\SalesOrder\Models\SalesOrder;
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

class SalesOrderCrudTest extends TestCase
{
    use RefreshDatabase;
    use CreatesTenant;
    use CreatesUser;
    use CreatesCustomer;
    use CreatesWarehouse;
    use CreatesProduct;

    protected Tenant $tenant;
    protected User $user;
    protected Customer $customer;
    protected Warehouse $warehouse;
    protected Product $product;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = $this->createTestTenant();
        $this->user = $this->createTestUser();
        Sanctum::actingAs($this->user);

        $this->customer = $this->createCustomer();
        $this->warehouse = $this->createWarehouse();
        $this->product = $this->createProduct();
    }

    public function test_sales_order_can_be_created(): void
    {
        $payload = [
            'customer_id' => $this->customer->id,
            'warehouse_id' => $this->warehouse->id,
            'order_date' => '2026-10-30',
            'expected_delivery_date' => '2026-11-05',
            'currency_code' => 'USD',
            'exchange_rate' => 1.0,
            'notes' => 'Urgent delivery request',
            'items' => [
                [
                    'product_id' => $this->product->id,
                    'quantity' => 10,
                    'unit_price' => 100,
                    'discount_type' => 'percentage',
                    'discount_rate' => 10, // 10% discount => $100 off => $900
                    'tax_rate' => 5, // 5% tax on $900 => $45 => $945
                ],
            ],
        ];

        $response = $this->postJson(route('sales-orders.store'), $payload);

        $response->assertCreated()
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'uuid',
                    'order_number',
                    'status',
                    'customer' => ['uuid', 'name', 'code'],
                    'warehouse' => ['uuid', 'name'],
                    'subtotal_amount',
                    'discount_amount',
                    'tax_total_amount',
                    'grand_total_amount',
                    'items',
                ],
            ]);

        $this->assertDatabaseHas('sales_orders', [
            'tenant_id' => $this->tenant->id,
            'status' => 'draft',
            'grand_total_amount' => 945.0000,
        ]);
    }

    public function test_sales_order_can_be_created_using_uuids(): void
    {
        $payload = [
            'customer_uuid' => $this->customer->uuid,
            'warehouse_uuid' => $this->warehouse->uuid,
            'order_date' => '2026-10-30',
            'items' => [
                [
                    'product_uuid' => $this->product->uuid,
                    'quantity' => 5,
                    'unit_price' => 200,
                ],
            ],
        ];

        $response = $this->postJson(route('sales-orders.store'), $payload);

        $response->assertCreated();
        $uuid = $response->json('data.uuid');
        $this->assertNotNull($uuid);

        $this->assertDatabaseHas('sales_orders', [
            'tenant_id' => $this->tenant->id,
            'uuid' => $uuid,
            'grand_total_amount' => 1000.0000,
        ]);
    }

    public function test_sales_order_can_be_retrieved_by_uuid(): void
    {
        $createResponse = $this->postJson(route('sales-orders.store'), [
            'customer_id' => $this->customer->id,
            'warehouse_id' => $this->warehouse->id,
            'order_date' => '2026-10-30',
            'items' => [
                [
                    'product_id' => $this->product->id,
                    'quantity' => 2,
                    'unit_price' => 50,
                ],
            ],
        ]);

        $uuid = $createResponse->json('data.uuid');

        $response = $this->getJson(route('sales-orders.show', $uuid));

        $response->assertOk()
            ->assertJsonPath('data.uuid', $uuid)
            ->assertJsonPath('data.grand_total_amount', 100);
    }

    public function test_sales_order_can_be_updated_in_draft_state(): void
    {
        $createResponse = $this->postJson(route('sales-orders.store'), [
            'customer_id' => $this->customer->id,
            'warehouse_id' => $this->warehouse->id,
            'order_date' => '2026-10-30',
            'items' => [
                [
                    'product_id' => $this->product->id,
                    'quantity' => 2,
                    'unit_price' => 50,
                ],
            ],
        ]);

        $uuid = $createResponse->json('data.uuid');

        $updateResponse = $this->putJson(route('sales-orders.update', $uuid), [
            'notes' => 'Updated notes',
            'items' => [
                [
                    'product_id' => $this->product->id,
                    'quantity' => 4,
                    'unit_price' => 60,
                ],
            ],
        ]);

        $updateResponse->assertOk()
            ->assertJsonPath('data.grand_total_amount', 240)
            ->assertJsonPath('data.notes', 'Updated notes');
    }

    public function test_sales_order_can_be_deleted_in_draft_state(): void
    {
        $createResponse = $this->postJson(route('sales-orders.store'), [
            'customer_id' => $this->customer->id,
            'warehouse_id' => $this->warehouse->id,
            'order_date' => '2026-10-30',
            'items' => [
                [
                    'product_id' => $this->product->id,
                    'quantity' => 1,
                    'unit_price' => 50,
                ],
            ],
        ]);

        $uuid = $createResponse->json('data.uuid');

        $response = $this->deleteJson(route('sales-orders.destroy', $uuid));

        $response->assertOk();

        app(TenantManager::class)->setTenant($this->tenant);
        $this->assertSoftDeleted('sales_orders', [
            'uuid' => $uuid,
        ]);
    }

    public function test_sales_order_items_can_be_retrieved(): void
    {
        $createResponse = $this->postJson(route('sales-orders.store'), [
            'customer_id' => $this->customer->id,
            'warehouse_id' => $this->warehouse->id,
            'order_date' => '2026-10-30',
            'items' => [
                [
                    'product_id' => $this->product->id,
                    'quantity' => 3,
                    'unit_price' => 40,
                ],
            ],
        ]);

        $uuid = $createResponse->json('data.uuid');

        $response = $this->getJson(route('sales-orders.items', $uuid));

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'uuid',
                        'item_name',
                        'quantity',
                        'unit_price',
                        'total_amount',
                    ],
                ],
            ]);
    }
}
