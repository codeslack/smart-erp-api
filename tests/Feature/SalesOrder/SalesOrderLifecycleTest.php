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

class SalesOrderLifecycleTest extends TestCase
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

    public function test_sales_order_can_be_confirmed(): void
    {
        $createResponse = $this->postJson(route('sales-orders.store'), [
            'customer_id' => $this->customer->id,
            'warehouse_id' => $this->warehouse->id,
            'order_date' => '2026-10-30',
            'items' => [
                [
                    'product_id' => $this->product->id,
                    'quantity' => 2,
                    'unit_price' => 100,
                ],
            ],
        ]);

        $uuid = $createResponse->json('data.uuid');

        $confirmResponse = $this->postJson(route('sales-orders.confirm', $uuid));

        $confirmResponse->assertOk()
            ->assertJsonPath('data.status', 'confirmed');

        $this->assertDatabaseHas('sales_orders', [
            'tenant_id' => $this->tenant->id,
            'uuid' => $uuid,
            'status' => 'confirmed',
            'confirmed_by' => $this->user->id,
        ]);
    }

    public function test_confirmed_sales_order_cannot_be_updated(): void
    {
        $createResponse = $this->postJson(route('sales-orders.store'), [
            'customer_id' => $this->customer->id,
            'warehouse_id' => $this->warehouse->id,
            'order_date' => '2026-10-30',
            'items' => [
                [
                    'product_id' => $this->product->id,
                    'quantity' => 2,
                    'unit_price' => 100,
                ],
            ],
        ]);

        $uuid = $createResponse->json('data.uuid');
        $this->postJson(route('sales-orders.confirm', $uuid))->assertOk();

        $updateResponse = $this->putJson(route('sales-orders.update', $uuid), [
            'notes' => 'Attempting update on confirmed order',
        ]);

        $updateResponse->assertStatus(422)
            ->assertJsonValidationErrors(['status']);
    }

    public function test_sales_order_can_be_cancelled(): void
    {
        $createResponse = $this->postJson(route('sales-orders.store'), [
            'customer_id' => $this->customer->id,
            'warehouse_id' => $this->warehouse->id,
            'order_date' => '2026-10-30',
            'items' => [
                [
                    'product_id' => $this->product->id,
                    'quantity' => 1,
                    'unit_price' => 150,
                ],
            ],
        ]);

        $uuid = $createResponse->json('data.uuid');

        $cancelResponse = $this->postJson(route('sales-orders.cancel', $uuid), [
            'reason' => 'Customer changed mind',
        ]);

        $cancelResponse->assertOk()
            ->assertJsonPath('data.status', 'cancelled')
            ->assertJsonPath('data.cancellation_reason', 'Customer changed mind');

        $this->assertDatabaseHas('sales_orders', [
            'tenant_id' => $this->tenant->id,
            'uuid' => $uuid,
            'status' => 'cancelled',
            'cancellation_reason' => 'Customer changed mind',
        ]);
    }

    public function test_sales_orders_can_be_paginated_and_filtered(): void
    {
        $this->postJson(route('sales-orders.store'), [
            'customer_id' => $this->customer->id,
            'warehouse_id' => $this->warehouse->id,
            'order_date' => '2026-10-20',
            'items' => [
                ['product_id' => $this->product->id, 'quantity' => 1, 'unit_price' => 50],
            ],
        ])->assertCreated();

        $this->postJson(route('sales-orders.store'), [
            'customer_id' => $this->customer->id,
            'warehouse_id' => $this->warehouse->id,
            'order_date' => '2026-10-25',
            'items' => [
                ['product_id' => $this->product->id, 'quantity' => 2, 'unit_price' => 50],
            ],
        ])->assertCreated();

        $response = $this->getJson(route('sales-orders.index', [
            'customer_uuid' => $this->customer->uuid,
        ]));

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    '*' => ['uuid', 'order_number', 'status'],
                ],
            ]);

        $this->assertCount(2, $response->json('data'));
    }

    public function test_sales_order_tenant_isolation(): void
    {
        $createResponse = $this->postJson(route('sales-orders.store'), [
            'customer_id' => $this->customer->id,
            'warehouse_id' => $this->warehouse->id,
            'order_date' => '2026-10-30',
            'items' => [
                ['product_id' => $this->product->id, 'quantity' => 1, 'unit_price' => 50],
            ],
        ]);

        $uuid = $createResponse->json('data.uuid');

        // Create second tenant
        $secondTenant = $this->createTestTenant();
        $secondUser = $this->createTestUser(['tenant_id' => $secondTenant->id]);
        Sanctum::actingAs($secondUser);
        app(TenantManager::class)->setTenant($secondTenant);

        // Accessing first tenant's order must return 404
        $response = $this->getJson(route('sales-orders.show', $uuid));
        $response->assertNotFound();
    }
}
