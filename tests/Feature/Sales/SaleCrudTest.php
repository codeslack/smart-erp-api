<?php

namespace Tests\Feature\Sales;

use App\Core\Tenant\TenantManager;
use App\Modules\Accounting\Services\AccountingSetupService;
use App\Modules\Customer\Models\Customer;
use App\Modules\Product\Models\Product;
use App\Modules\Sales\Models\Sale;
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
use Tests\Support\GrantsSalesPermissions;
use Tests\TestCase;

class SaleCrudTest extends TestCase
{
    use RefreshDatabase;
    use CreatesTenant;
    use CreatesUser;
    use CreatesCustomer;
    use CreatesWarehouse;
    use CreatesProduct;
    use GrantsSalesPermissions;

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

        app(TenantManager::class)->setTenant($this->tenant);
        $this->grantSalesPermissions($this->user, $this->tenant);
        app(AccountingSetupService::class)->setup($this->tenant);

        $this->customer = $this->createCustomer();
        $this->warehouse = $this->createWarehouse();
        $this->product = $this->createProduct();
    }

    public function test_sale_can_be_created(): void
    {
        $payload = [
            'customer_id' => $this->customer->id,
            'warehouse_id' => $this->warehouse->id,
            'sale_date' => '2026-10-30',
            'due_date' => '2026-11-15',
            'currency_code' => 'USD',
            'exchange_rate' => 1.0,
            'notes' => 'Invoice for order 100',
            'items' => [
                [
                    'product_id' => $this->product->id,
                    'quantity' => 10,
                    'unit_price' => 100,
                    'discount_type' => 'percentage',
                    'discount_rate' => 10, // 10% on $1000 => $100 off => $900
                    'tax_rate' => 5, // 5% on $900 => $45 => $945
                ],
            ],
        ];

        $response = $this->postJson(route('sales.store'), $payload);

        $response->assertCreated()
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'uuid',
                    'sale_no',
                    'status',
                    'customer' => ['uuid', 'name', 'code'],
                    'warehouse' => ['uuid', 'name'],
                    'subtotal',
                    'discount_amount',
                    'tax_amount',
                    'grand_total',
                    'due_amount',
                    'items',
                ],
            ]);

        $this->assertDatabaseHas('sales', [
            'tenant_id' => $this->tenant->id,
            'status' => 'draft',
            'grand_total' => 945.0000,
            'due_amount' => 945.0000,
        ]);
    }

    public function test_sale_can_be_created_using_uuids(): void
    {
        $payload = [
            'customer_uuid' => $this->customer->uuid,
            'warehouse_uuid' => $this->warehouse->uuid,
            'sale_date' => '2026-10-30',
            'items' => [
                [
                    'product_uuid' => $this->product->uuid,
                    'quantity' => 5,
                    'unit_price' => 200,
                ],
            ],
        ];

        $response = $this->postJson(route('sales.store'), $payload);

        $response->assertCreated();
        $uuid = $response->json('data.uuid');
        $this->assertNotNull($uuid);

        $this->assertDatabaseHas('sales', [
            'tenant_id' => $this->tenant->id,
            'uuid' => $uuid,
            'grand_total' => 1000.0000,
        ]);
    }

    public function test_sale_can_be_retrieved_by_uuid(): void
    {
        $createResponse = $this->postJson(route('sales.store'), [
            'customer_id' => $this->customer->id,
            'warehouse_id' => $this->warehouse->id,
            'sale_date' => '2026-10-30',
            'items' => [
                [
                    'product_id' => $this->product->id,
                    'quantity' => 2,
                    'unit_price' => 50,
                ],
            ],
        ]);

        $uuid = $createResponse->json('data.uuid');

        $response = $this->getJson(route('sales.show', $uuid));

        $response->assertOk()
            ->assertJsonPath('data.uuid', $uuid)
            ->assertJsonPath('data.grand_total', 100);
    }

    public function test_sale_can_be_updated_in_draft_state(): void
    {
        $createResponse = $this->postJson(route('sales.store'), [
            'customer_id' => $this->customer->id,
            'warehouse_id' => $this->warehouse->id,
            'sale_date' => '2026-10-30',
            'items' => [
                [
                    'product_id' => $this->product->id,
                    'quantity' => 2,
                    'unit_price' => 50,
                ],
            ],
        ]);

        $uuid = $createResponse->json('data.uuid');

        $updateResponse = $this->putJson(route('sales.update', $uuid), [
            'notes' => 'Updated sale notes',
            'items' => [
                [
                    'product_id' => $this->product->id,
                    'quantity' => 4,
                    'unit_price' => 60,
                ],
            ],
        ]);

        $updateResponse->assertOk()
            ->assertJsonPath('data.grand_total', 240)
            ->assertJsonPath('data.notes', 'Updated sale notes');
    }

    public function test_updating_header_amounts_recalculates_sale_totals_without_resending_items(): void
    {
        $createResponse = $this->postJson(route('sales.store'), [
            'customer_id' => $this->customer->id,
            'warehouse_id' => $this->warehouse->id,
            'sale_date' => '2026-10-30',
            'items' => [[
                'product_id' => $this->product->id,
                'quantity' => 2,
                'unit_price' => 100,
                'discount_type' => 'percentage',
                'discount_rate' => 10,
                'tax_rate' => 10,
            ]],
        ])->assertCreated();

        $uuid = $createResponse->json('data.uuid');

        $updateResponse = $this->putJson(route('sales.update', $uuid), [
            'discount_amount' => 10,
            'charges_subtotal' => 5,
            'charges_tax_amount' => 1,
            'rounding_amount' => 0.5,
        ]);

        $updateResponse->assertOk()
            ->assertJsonPath('data.subtotal', 180)
            ->assertJsonPath('data.discount_amount', 30)
            ->assertJsonPath('data.tax_amount', 18)
            ->assertJsonPath('data.grand_total', 194.5)
            ->assertJsonPath('data.due_amount', 194.5);

        $this->assertDatabaseHas('sales', [
            'tenant_id' => $this->tenant->id,
            'uuid' => $uuid,
            'discount_amount' => 30,
            'grand_total' => 194.5,
            'due_amount' => 194.5,
        ]);
    }

    public function test_replacing_items_does_not_reapply_existing_item_discounts_as_header_discount(): void
    {
        $createResponse = $this->postJson(route('sales.store'), [
            'customer_id' => $this->customer->id,
            'warehouse_id' => $this->warehouse->id,
            'sale_date' => '2026-10-30',
            'items' => [[
                'product_id' => $this->product->id,
                'quantity' => 2,
                'unit_price' => 100,
                'discount_type' => 'percentage',
                'discount_rate' => 10,
            ]],
        ])->assertCreated();

        $uuid = $createResponse->json('data.uuid');

        $updateResponse = $this->putJson(route('sales.update', $uuid), [
            'items' => [[
                'product_id' => $this->product->id,
                'quantity' => 3,
                'unit_price' => 100,
                'discount_type' => 'percentage',
                'discount_rate' => 10,
            ]],
        ]);

        $updateResponse->assertOk()
            ->assertJsonPath('data.subtotal', 270)
            ->assertJsonPath('data.discount_amount', 30)
            ->assertJsonPath('data.grand_total', 270);
    }

    public function test_sale_can_be_deleted_in_draft_state(): void
    {
        $createResponse = $this->postJson(route('sales.store'), [
            'customer_id' => $this->customer->id,
            'warehouse_id' => $this->warehouse->id,
            'sale_date' => '2026-10-30',
            'items' => [
                [
                    'product_id' => $this->product->id,
                    'quantity' => 1,
                    'unit_price' => 50,
                ],
            ],
        ]);

        $uuid = $createResponse->json('data.uuid');

        $response = $this->deleteJson(route('sales.destroy', $uuid));
        $response->assertOk();

        app(TenantManager::class)->setTenant($this->tenant);
        $this->assertSoftDeleted('sales', [
            'uuid' => $uuid,
        ]);
    }

    public function test_sale_rejects_internal_fields_on_nested_items(): void
    {
        $response = $this->postJson(route('sales.store'), [
            'customer_id' => $this->customer->id,
            'warehouse_id' => $this->warehouse->id,
            'sale_date' => '2026-10-30',
            'items' => [
                [
                    'product_id' => $this->product->id,
                    'quantity' => 2,
                    'unit_price' => 50,
                    'base_quantity' => 500,
                    'cost_price' => 0.01,
                    'tenant_id' => $this->tenant->id + 1,
                ],
            ],
        ]);

        $response
            ->assertStatus(422)
            ->assertJsonValidationErrors('items.0');
    }

    public function test_sale_update_rejects_internal_nested_item_fields(): void
    {
        $createResponse = $this->postJson(route('sales.store'), [
            'customer_id' => $this->customer->id,
            'warehouse_id' => $this->warehouse->id,
            'sale_date' => '2026-10-30',
            'items' => [
                [
                    'product_id' => $this->product->id,
                    'quantity' => 1,
                    'unit_price' => 50,
                ],
            ],
        ]);

        $uuid = $createResponse->json('data.uuid');

        $this->putJson(route('sales.update', $uuid), [
            'items' => [
                [
                    'product_id' => $this->product->id,
                    'quantity' => 2,
                    'unit_price' => 50,
                    'base_quantity' => 500,
                    'cost_price' => 0.01,
                    'tenant_id' => $this->tenant->id + 1,
                ],
            ],
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('items.0');
    }

    public function test_sale_rejects_client_supplied_batch_and_serial_ids(): void
    {
        $response = $this->postJson(route('sales.store'), [
            'customer_id' => $this->customer->id,
            'warehouse_id' => $this->warehouse->id,
            'sale_date' => now()->toDateString(),
            'items' => [[
                'product_id' => $this->product->id,
                'quantity' => 1,
                'unit_price' => 50,
                'batches' => [[
                    'batch_no' => 'BATCH-CLIENT',
                    'quantity' => 1,
                    'product_batch_id' => 123,
                ]],
                'serials' => [[
                    'serial_number' => 'SERIAL-CLIENT',
                    'product_serial_id' => 123,
                ]],
            ]],
        ]);

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'items.0.batches.0',
                'items.0.serials.0',
            ]);
    }

    public function test_sale_update_rejects_client_supplied_batch_and_serial_ids(): void
    {
        $createResponse = $this->postJson(route('sales.store'), [
            'customer_id' => $this->customer->id,
            'warehouse_id' => $this->warehouse->id,
            'sale_date' => now()->toDateString(),
            'items' => [[
                'product_id' => $this->product->id,
                'quantity' => 1,
                'unit_price' => 50,
            ]],
        ]);

        $uuid = $createResponse->json('data.uuid');

        $response = $this->putJson(route('sales.update', $uuid), [
            'items' => [[
                'product_id' => $this->product->id,
                'quantity' => 1,
                'unit_price' => 50,
                'batches' => [[
                    'batch_no' => 'BATCH-CLIENT',
                    'quantity' => 1,
                    'product_batch_id' => 123,
                ]],
                'serials' => [[
                    'serial_number' => 'SERIAL-CLIENT',
                    'product_serial_id' => 123,
                ]],
            ]],
        ]);

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'items.0.batches.0',
                'items.0.serials.0',
            ]);
    }

    public function test_sale_items_can_be_retrieved(): void
    {
        $createResponse = $this->postJson(route('sales.store'), [
            'customer_id' => $this->customer->id,
            'warehouse_id' => $this->warehouse->id,
            'sale_date' => '2026-10-30',
            'items' => [
                [
                    'product_id' => $this->product->id,
                    'quantity' => 3,
                    'unit_price' => 40,
                ],
            ],
        ]);

        $uuid = $createResponse->json('data.uuid');

        $response = $this->getJson(route('sales.items', $uuid));

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'uuid',
                        'item_name',
                        'quantity',
                        'unit_price',
                        'line_total',
                    ],
                ],
            ]);
    }
}
