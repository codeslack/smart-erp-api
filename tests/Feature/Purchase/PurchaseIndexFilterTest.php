<?php

namespace Tests\Feature\Purchase;

use App\Modules\Tenant\Models\Tenant;
use App\Modules\User\Models\User;
use App\Modules\Supplier\Models\Supplier;
use App\Modules\Warehouse\Models\Warehouse;
use App\Modules\Purchase\Models\Purchase;
use App\Modules\Purchase\Enums\PurchaseStatusEnum;
use App\Modules\Accounting\Services\AccountingSetupService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Support\CreatesTenant;
use Tests\Support\CreatesUser;
use Tests\TestCase;

class PurchaseIndexFilterTest extends TestCase
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

        app(AccountingSetupService::class)->setup($this->tenant);

        $this->supplier = Supplier::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Supplier Filter Test',
            'is_active' => true,
        ]);

        $this->warehouse = Warehouse::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Warehouse Filter Test',
            'code' => 'WH-FILT-01',
            'is_active' => true,
        ]);
    }

    public function test_purchases_index_returns_paginated_list_with_filters(): void
    {
        Purchase::create([
            'tenant_id' => $this->tenant->id,
            'supplier_id' => $this->supplier->id,
            'warehouse_id' => $this->warehouse->id,
            'purchase_no' => 'PUR-FILTER-001',
            'purchase_date' => now()->toDateString(),
            'subtotal' => 500,
            'grand_total' => 500,
            'paid_amount' => 0,
            'due_amount' => 500,
            'status' => PurchaseStatusEnum::DRAFT->value,
        ]);

        $response = $this->getJson(route('purchases.index', [
            'status' => 'draft',
            'supplier_uuid' => $this->supplier->uuid,
            'per_page' => 10,
        ]));

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.0.purchase_no', 'PUR-FILTER-001')
            ->assertJsonStructure(['data', 'links', 'meta']);
    }
}

