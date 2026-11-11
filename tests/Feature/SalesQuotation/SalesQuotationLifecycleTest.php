<?php

namespace Tests\Feature\SalesQuotation;

use App\Core\Tenant\TenantManager;
use App\Modules\Accounting\Services\AccountingSetupService;
use App\Modules\Customer\Models\Customer;
use App\Modules\Product\Models\Product;
use App\Modules\SalesOrder\Models\SalesOrder;
use App\Modules\SalesQuotation\Enums\SalesQuotationStatusEnum;
use App\Modules\SalesQuotation\Models\SalesQuotation;
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

class SalesQuotationLifecycleTest extends TestCase
{
    use RefreshDatabase, CreatesTenant, CreatesUser, CreatesCustomer, CreatesWarehouse, CreatesProduct, GrantsSalesPermissions;

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
        $this->product = $this->createProduct(['purchase_price' => 50, 'selling_price' => 100]);
    }

    public function test_sales_quotation_can_be_marked_as_sent(): void
    {
        $createRes = $this->postJson(route('sales-quotations.store'), [
            'customer_uuid' => $this->customer->uuid,
            'quotation_date' => now()->toDateString(),
            'items' => [
                ['product_uuid' => $this->product->uuid, 'quantity' => 2, 'unit_price' => 100],
            ],
        ]);

        $uuid = $createRes->json('data.uuid');

        $sendRes = $this->postJson(route('sales-quotations.send', ['uuid' => $uuid]));
        $sendRes->assertOk()->assertJsonPath('data.status', 'sent');

        $this->assertDatabaseHas('sales_quotations', [
            'uuid' => $uuid,
            'status' => SalesQuotationStatusEnum::SENT->value,
        ]);
    }

    public function test_sales_quotation_can_be_accepted(): void
    {
        $createRes = $this->postJson(route('sales-quotations.store'), [
            'customer_uuid' => $this->customer->uuid,
            'quotation_date' => now()->toDateString(),
            'items' => [
                ['product_uuid' => $this->product->uuid, 'quantity' => 2, 'unit_price' => 100],
            ],
        ]);

        $uuid = $createRes->json('data.uuid');

        $acceptRes = $this->postJson(route('sales-quotations.accept', ['uuid' => $uuid]));
        $acceptRes->assertOk()->assertJsonPath('data.status', 'accepted');

        $this->assertDatabaseHas('sales_quotations', [
            'uuid' => $uuid,
            'status' => SalesQuotationStatusEnum::ACCEPTED->value,
        ]);
    }

    public function test_sales_quotation_can_be_rejected(): void
    {
        $createRes = $this->postJson(route('sales-quotations.store'), [
            'customer_uuid' => $this->customer->uuid,
            'quotation_date' => now()->toDateString(),
            'items' => [
                ['product_uuid' => $this->product->uuid, 'quantity' => 2, 'unit_price' => 100],
            ],
        ]);

        $uuid = $createRes->json('data.uuid');

        $rejectRes = $this->postJson(route('sales-quotations.reject', ['uuid' => $uuid]), [
            'reason' => 'Price is too high',
        ]);
        $rejectRes->assertOk()->assertJsonPath('data.status', 'rejected');

        $this->assertDatabaseHas('sales_quotations', [
            'uuid' => $uuid,
            'status' => SalesQuotationStatusEnum::REJECTED->value,
            'rejection_reason' => 'Price is too high',
        ]);
    }

    public function test_sales_quotation_can_be_cancelled(): void
    {
        $createRes = $this->postJson(route('sales-quotations.store'), [
            'customer_uuid' => $this->customer->uuid,
            'quotation_date' => now()->toDateString(),
            'items' => [
                ['product_uuid' => $this->product->uuid, 'quantity' => 2, 'unit_price' => 100],
            ],
        ]);

        $uuid = $createRes->json('data.uuid');

        $cancelRes = $this->postJson(route('sales-quotations.cancel', ['uuid' => $uuid]), [
            'reason' => 'Customer no longer interested',
        ]);
        $cancelRes->assertOk()->assertJsonPath('data.status', 'cancelled');

        $this->assertDatabaseHas('sales_quotations', [
            'uuid' => $uuid,
            'status' => SalesQuotationStatusEnum::CANCELLED->value,
        ]);
    }

    public function test_sales_quotation_can_be_converted_to_sales_order(): void
    {
        $createRes = $this->postJson(route('sales-quotations.store'), [
            'customer_uuid' => $this->customer->uuid,
            'warehouse_uuid' => $this->warehouse->uuid,
            'quotation_date' => now()->toDateString(),
            'items' => [
                ['product_uuid' => $this->product->uuid, 'quantity' => 3, 'unit_price' => 100],
            ],
        ]);

        $uuid = $createRes->json('data.uuid');

        $convertRes = $this->postJson(route('sales-quotations.convert-to-order', ['uuid' => $uuid]));
        $convertRes->assertOk();

        $orderUuid = $convertRes->json('data.uuid');
        $this->assertNotNull($orderUuid);

        app(TenantManager::class)->setTenant($this->tenant);
        $quotation = SalesQuotation::query()->where('uuid', $uuid)->first();
        $this->assertEquals(SalesQuotationStatusEnum::CONVERTED_TO_ORDER->value, $quotation->status);
        $this->assertNotNull($quotation->converted_sales_order_id);

        $salesOrder = SalesOrder::query()->where('uuid', $orderUuid)->first();
        $this->assertNotNull($salesOrder);
        $this->assertEquals($quotation->id, $salesOrder->sales_quotation_id);
    }

    public function test_cannot_convert_already_converted_sales_quotation(): void
    {
        $createRes = $this->postJson(route('sales-quotations.store'), [
            'customer_uuid' => $this->customer->uuid,
            'warehouse_uuid' => $this->warehouse->uuid,
            'quotation_date' => now()->toDateString(),
            'items' => [
                ['product_uuid' => $this->product->uuid, 'quantity' => 1, 'unit_price' => 100],
            ],
        ]);

        $uuid = $createRes->json('data.uuid');
        $this->postJson(route('sales-quotations.convert-to-order', ['uuid' => $uuid]))->assertOk();

        // Second conversion attempt should fail
        $secondConvert = $this->postJson(route('sales-quotations.convert-to-order', ['uuid' => $uuid]));
        $secondConvert->assertUnprocessable();
    }

    public function test_cannot_modify_converted_sales_quotation(): void
    {
        $createRes = $this->postJson(route('sales-quotations.store'), [
            'customer_uuid' => $this->customer->uuid,
            'warehouse_uuid' => $this->warehouse->uuid,
            'quotation_date' => now()->toDateString(),
            'items' => [
                ['product_uuid' => $this->product->uuid, 'quantity' => 1, 'unit_price' => 100],
            ],
        ]);

        $uuid = $createRes->json('data.uuid');
        $this->postJson(route('sales-quotations.convert-to-order', ['uuid' => $uuid]))->assertOk();

        $updateRes = $this->putJson(route('sales-quotations.update', ['uuid' => $uuid]), [
            'notes' => 'Attempting to change converted quotation',
        ]);
        $updateRes->assertUnprocessable();
    }
}
