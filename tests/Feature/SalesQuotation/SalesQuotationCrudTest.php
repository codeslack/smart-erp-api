<?php

namespace Tests\Feature\SalesQuotation;

use App\Core\Tenant\TenantManager;
use App\Modules\Accounting\Services\AccountingSetupService;
use App\Modules\Customer\Models\Customer;
use App\Modules\Product\Models\Product;
use App\Modules\SalesQuotation\Enums\SalesQuotationStatusEnum;
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

class SalesQuotationCrudTest extends TestCase
{
    use RefreshDatabase, CreatesTenant, CreatesUser, CreatesCustomer, CreatesWarehouse, CreatesProduct;

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
        app(AccountingSetupService::class)->setup($this->tenant);

        $this->customer = $this->createCustomer();
        $this->warehouse = $this->createWarehouse();
        $this->product = $this->createProduct(['purchase_price' => 50, 'selling_price' => 100]);
    }

    public function test_sales_quotation_can_be_created(): void
    {
        $payload = [
            'customer_uuid' => $this->customer->uuid,
            'warehouse_uuid' => $this->warehouse->uuid,
            'quotation_date' => now()->toDateString(),
            'expiry_date' => now()->addDays(30)->toDateString(),
            'notes' => 'Valid for 30 days',
            'items' => [
                [
                    'product_uuid' => $this->product->uuid,
                    'quantity' => 5,
                    'unit_price' => 100,
                    'discount_type' => 'percentage',
                    'discount_rate' => 10,
                    'tax_rate' => 10,
                ],
            ],
        ];

        $res = $this->postJson(route('sales-quotations.store'), $payload);
        $res->assertCreated()->assertJsonStructure([
            'success', 'message', 'data' => ['uuid', 'quotation_no', 'status', 'customer' => ['uuid', 'name'], 'warehouse' => ['uuid', 'name'], 'grand_total', 'items'],
        ]);

        $this->assertDatabaseHas('sales_quotations', [
            'tenant_id' => $this->tenant->id,
            'status' => SalesQuotationStatusEnum::DRAFT->value,
            'grand_total' => 495.0000,
        ]);
    }

    public function test_sales_quotation_exposes_only_uuid_not_internal_id(): void
    {
        $res = $this->postJson(route('sales-quotations.store'), [
            'customer_uuid' => $this->customer->uuid,
            'quotation_date' => now()->toDateString(),
            'items' => [['product_uuid' => $this->product->uuid, 'quantity' => 1, 'unit_price' => 100]],
        ]);

        $res->assertCreated();
        $data = $res->json('data');
        $this->assertArrayNotHasKey('id', $data);
        $this->assertArrayHasKey('uuid', $data);

        if (! empty($data['items'])) {
            $this->assertArrayNotHasKey('id', $data['items'][0]);
            $this->assertArrayHasKey('uuid', $data['items'][0]);
            $this->assertArrayNotHasKey('sales_quotation_id', $data['items'][0]);
            $this->assertArrayNotHasKey('product_id', $data['items'][0]);
        }
    }

    public function test_sales_quotation_can_be_retrieved_by_uuid(): void
    {
        $createRes = $this->postJson(route('sales-quotations.store'), [
            'customer_uuid' => $this->customer->uuid,
            'quotation_date' => now()->toDateString(),
            'items' => [['product_uuid' => $this->product->uuid, 'quantity' => 2, 'unit_price' => 50]],
        ]);

        $uuid = $createRes->json('data.uuid');
        $res = $this->getJson(route('sales-quotations.show', ['uuid' => $uuid]));
        $res->assertOk()->assertJsonPath('data.uuid', $uuid)->assertJsonPath('data.grand_total', 100);
    }

    public function test_sales_quotation_can_be_updated_in_draft_state(): void
    {
        $createRes = $this->postJson(route('sales-quotations.store'), [
            'customer_uuid' => $this->customer->uuid,
            'quotation_date' => now()->toDateString(),
            'items' => [['product_uuid' => $this->product->uuid, 'quantity' => 2, 'unit_price' => 50]],
        ]);

        $uuid = $createRes->json('data.uuid');
        $updateRes = $this->putJson(route('sales-quotations.update', ['uuid' => $uuid]), [
            'notes' => 'Updated quotation notes',
            'items' => [['product_uuid' => $this->product->uuid, 'quantity' => 4, 'unit_price' => 50]],
        ]);

        $updateRes->assertOk()->assertJsonPath('data.grand_total', 200);
    }

    public function test_sales_quotation_items_can_be_retrieved(): void
    {
        $createRes = $this->postJson(route('sales-quotations.store'), [
            'customer_uuid' => $this->customer->uuid,
            'quotation_date' => now()->toDateString(),
            'items' => [['product_uuid' => $this->product->uuid, 'quantity' => 3, 'unit_price' => 75]],
        ]);

        $uuid = $createRes->json('data.uuid');
        $res = $this->getJson(route('sales-quotations.items', ['uuid' => $uuid]));
        $res->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.quantity', 3);
    }

    public function test_sales_quotation_can_be_deleted_in_draft_state(): void
    {
        $createRes = $this->postJson(route('sales-quotations.store'), [
            'customer_uuid' => $this->customer->uuid,
            'quotation_date' => now()->toDateString(),
            'items' => [['product_uuid' => $this->product->uuid, 'quantity' => 1, 'unit_price' => 100]],
        ]);

        $uuid = $createRes->json('data.uuid');
        $this->deleteJson(route('sales-quotations.destroy', ['uuid' => $uuid]))->assertOk();
        $this->assertSoftDeleted('sales_quotations', ['uuid' => $uuid]);
    }

    public function test_tenant_isolation_prevents_cross_tenant_access(): void
    {
        $createRes = $this->postJson(route('sales-quotations.store'), [
            'customer_uuid' => $this->customer->uuid,
            'quotation_date' => now()->toDateString(),
            'items' => [['product_uuid' => $this->product->uuid, 'quantity' => 1, 'unit_price' => 100]],
        ]);

        $uuid = $createRes->json('data.uuid');
        $otherTenant = $this->createTestTenant();
        $otherUser = $this->createTestUser(['tenant_id' => $otherTenant->id]);
        Sanctum::actingAs($otherUser);
        app(TenantManager::class)->setTenant($otherTenant);

        $this->getJson(route('sales-quotations.show', ['uuid' => $uuid]))->assertNotFound();
    }
}
