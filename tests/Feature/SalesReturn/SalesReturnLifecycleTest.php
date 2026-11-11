<?php

namespace Tests\Feature\SalesReturn;

use App\Core\Tenant\TenantManager;
use App\Modules\Accounting\Enums\JournalVoucherTypeEnum;
use App\Modules\Accounting\Models\JournalEntry;
use App\Modules\Accounting\Services\AccountingSetupService;
use App\Modules\Customer\Models\Customer;
use App\Modules\Inventory\Data\InventoryMovementData;
use App\Modules\Inventory\Enums\InventoryTransactionTypeEnum;
use App\Modules\Inventory\Enums\ProductSerialStatusEnum;
use App\Modules\Inventory\Models\ProductBatch;
use App\Modules\Inventory\Models\ProductSerial;
use App\Modules\Inventory\Models\ProductStock;
use App\Modules\Inventory\Services\InventoryPostingService;
use App\Modules\Inventory\Services\InventorySerialService;
use App\Modules\Product\Enums\InventoryTrackingTypeEnum;
use App\Modules\Product\Enums\ProductTypeEnum;
use App\Modules\Product\Models\Product;
use App\Modules\ProductUnit\Models\ProductUnit;
use App\Modules\Unit\Models\Unit;
use App\Modules\Purchase\Services\PurchaseService;
use App\Modules\Sales\Models\Sale;
use App\Modules\Sales\Services\SaleService;
use App\Modules\SalesReturn\Models\SalesReturn;
use App\Modules\Supplier\Models\Supplier;
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

class SalesReturnLifecycleTest extends TestCase
{
    use RefreshDatabase, CreatesTenant, CreatesUser, CreatesCustomer, CreatesWarehouse, CreatesProduct;

    protected Tenant $tenant;
    protected User $user;
    protected Customer $customer;
    protected Warehouse $warehouse;
    protected Product $product;
    protected Sale $sale;

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

        $supplier = Supplier::create(['tenant_id' => $this->tenant->id, 'name' => 'Supplier', 'is_active' => true]);
        $purchaseSvc = app(PurchaseService::class);
        $purchase = $purchaseSvc->create([
            'supplier_uuid' => $supplier->uuid,
            'warehouse_uuid' => $this->warehouse->uuid,
            'purchase_date' => now()->toDateString(),
            'items' => [['product_uuid' => $this->product->uuid, 'quantity' => 10, 'unit_cost' => 50]],
        ]);
        $purchaseSvc->confirm($purchase);

        $saleSvc = app(SaleService::class);
        $this->sale = $saleSvc->create([
            'customer_uuid' => $this->customer->uuid,
            'warehouse_uuid' => $this->warehouse->uuid,
            'sale_date' => now()->toDateString(),
            'items' => [['product_uuid' => $this->product->uuid, 'quantity' => 5, 'unit_price' => 100, 'unit_cost' => 50]],
        ]);
        $this->sale = $saleSvc->confirm($this->sale->uuid);
    }

    public function test_sales_return_can_be_confirmed_restoring_stock_and_posting_gl(): void
    {
        $saleItem = $this->sale->items->first();
        $createRes = $this->postJson(route('sales-returns.store'), [
            'sale_uuid' => $this->sale->uuid,
            'return_date' => now()->toDateString(),
            'items' => [['sale_item_uuid' => $saleItem->uuid, 'returned_quantity' => 2, 'unit_price' => 100, 'unit_cost' => 50]],
        ]);

        $createRes->assertCreated();
        $uuid = $createRes->json('data.uuid');

        $confirmRes = $this->postJson(route('sales-returns.confirm', ['uuid' => $uuid]));
        $confirmRes->assertOk();
        $this->assertContains($confirmRes->json('data.status'), ['approved', 'confirmed']);

        app(TenantManager::class)->setTenant($this->tenant);
        $salesReturn = SalesReturn::query()->where('uuid', $uuid)->first();
        $this->assertNotNull($salesReturn);

        $stock = ProductStock::query()->where('product_id', $this->product->id)->where('warehouse_id', $this->warehouse->id)->first();
        $this->assertEquals(7, (float) $stock->quantity);

        $this->assertDatabaseHas('stock_ledgers', [
            'product_id' => $this->product->id,
            'warehouse_id' => $this->warehouse->id,
            'quantity_in' => 2,
            'referenceable_id' => $salesReturn->id,
        ]);

        $journalEntry = JournalEntry::query()
            ->where('voucher_type', JournalVoucherTypeEnum::SALES_RETURN->value)
            ->where('reference_id', $salesReturn->id)
            ->first();
        $this->assertNotNull($journalEntry);

        $refreshedSale = Sale::query()->find($this->sale->id);
        $this->assertEquals(300, (float) $refreshedSale->due_amount);
    }

    public function test_cannot_confirm_already_confirmed_sales_return(): void
    {
        $saleItem = $this->sale->items->first();
        $createRes = $this->postJson(route('sales-returns.store'), [
            'sale_uuid' => $this->sale->uuid,
            'return_date' => now()->toDateString(),
            'items' => [['sale_item_uuid' => $saleItem->uuid, 'returned_quantity' => 1, 'unit_price' => 100]],
        ]);

        $uuid = $createRes->json('data.uuid');
        $this->postJson(route('sales-returns.confirm', ['uuid' => $uuid]))->assertOk();
        $this->postJson(route('sales-returns.confirm', ['uuid' => $uuid]))->assertUnprocessable();
    }

    public function test_cannot_return_more_than_sold_quantity(): void
    {
        $saleItem = $this->sale->items->first();
        $res = $this->postJson(route('sales-returns.store'), [
            'sale_uuid' => $this->sale->uuid,
            'return_date' => now()->toDateString(),
            'items' => [['sale_item_uuid' => $saleItem->uuid, 'returned_quantity' => 6, 'unit_price' => 100]],
        ]);
        $res->assertUnprocessable();
    }

    public function test_update_cannot_change_customer_away_from_source_sale(): void
    {
        $saleItem = $this->sale->items->first();

        $createResponse = $this->postJson(route('sales-returns.store'), [
            'sale_uuid' => $this->sale->uuid,
            'return_date' => now()->toDateString(),
            'items' => [[
                'sale_item_uuid' => $saleItem->uuid,
                'returned_quantity' => 1,
                'unit_price' => 100,
            ]],
        ]);

        $createResponse->assertCreated();
        $returnUuid = $createResponse->json('data.uuid');
        app(TenantManager::class)->setTenant($this->tenant);
        $this->assertSame($this->tenant->id, tenantId());
        $otherCustomer = $this->createCustomer();

        $this->putJson(route('sales-returns.update', ['uuid' => $returnUuid]), [
            'customer_uuid' => $otherCustomer->uuid,
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('customer_uuid');
    }

    public function test_sales_return_rejects_duplicate_lines_exceeding_sold_quantity(): void
    {
        $saleItem = $this->sale->items->first();

        $response = $this->postJson(route('sales-returns.store'), [
            'sale_uuid' => $this->sale->uuid,
            'return_date' => now()->toDateString(),
            'items' => [
                [
                    'sale_item_uuid' => $saleItem->uuid,
                    'returned_quantity' => 3,
                    'unit_price' => 100,
                ],
                [
                    'sale_item_uuid' => $saleItem->uuid,
                    'returned_quantity' => 3,
                    'unit_price' => 100,
                ],
            ],
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors('items.0.returned_quantity');
    }

    public function test_sales_return_restores_converted_base_quantity(): void
    {
        $baseUnit = Unit::create([
            'tenant_id' => $this->tenant->id,
            'code' => 'RET-EACH',
            'name' => 'Return Each',
            'short_name' => 'ea',
            'is_active' => true,
        ]);

        $boxUnit = Unit::create([
            'tenant_id' => $this->tenant->id,
            'code' => 'RET-BOX',
            'name' => 'Return Box',
            'short_name' => 'box',
            'is_active' => true,
        ]);

        $this->product->update(['unit_id' => $baseUnit->id]);

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

        $supplier = Supplier::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Return Conversion Supplier',
            'is_active' => true,
        ]);

        $purchaseService = app(PurchaseService::class);
        $extraPurchase = $purchaseService->create([
            'supplier_uuid' => $supplier->uuid,
            'warehouse_uuid' => $this->warehouse->uuid,
            'purchase_date' => now()->toDateString(),
            'items' => [[
                'product_uuid' => $this->product->uuid,
                'unit_uuid' => $baseUnit->uuid,
                'quantity' => 10,
                'unit_cost' => 50,
            ]],
        ]);
        $purchaseService->confirm($extraPurchase);

        $saleService = app(SaleService::class);
        $unitSale = $saleService->create([
            'customer_uuid' => $this->customer->uuid,
            'warehouse_uuid' => $this->warehouse->uuid,
            'sale_date' => now()->toDateString(),
            'items' => [[
                'product_uuid' => $this->product->uuid,
                'unit_uuid' => $boxUnit->uuid,
                'quantity' => 1,
                'unit_price' => 1000,
            ]],
        ]);
        $unitSale = $saleService->confirm($unitSale->uuid);
        $saleItem = $unitSale->items->first();

        $this->assertEquals(10, (float) $saleItem->base_quantity);

        $response = $this->postJson(route('sales-returns.store'), [
            'sale_uuid' => $unitSale->uuid,
            'return_date' => now()->toDateString(),
            'items' => [[
                'sale_item_uuid' => $saleItem->uuid,
                'returned_quantity' => 1,
                'unit_price' => 1000,
            ]],
        ]);

        $response->assertCreated();
        $returnUuid = $response->json('data.uuid');

        $this->postJson(route('sales-returns.confirm', ['uuid' => $returnUuid]))
            ->assertOk();

        app(TenantManager::class)->setTenant($this->tenant);

        $stock = ProductStock::query()
            ->where('product_id', $this->product->id)
            ->where('warehouse_id', $this->warehouse->id)
            ->firstOrFail();

        $this->assertEquals(15, (float) $stock->quantity);

        $this->assertDatabaseHas('stock_ledgers', [
            'product_id' => $this->product->id,
            'warehouse_id' => $this->warehouse->id,
            'quantity_in' => 10,
            'transaction_type' => 'SALE_RETURN',
        ]);
    }

    public function test_sales_return_restores_sold_serial_and_rejects_returning_it_twice(): void
    {
        $this->product->update([
            'inventory_tracking_type' => InventoryTrackingTypeEnum::SERIAL,
        ]);

        $serial = app(InventorySerialService::class)->register(
            productId: $this->product->id,
            warehouseId: $this->warehouse->id,
            serialNumber: 'SERIAL-RETURN-001',
            purchaseCost: 50,
        );

        $posting = app(InventoryPostingService::class);
        $posting->stockIn(new InventoryMovementData(
            productId: $this->product->id,
            productVariantId: null,
            warehouseId: $this->warehouse->id,
            quantity: 1,
            unitCost: 50,
            transactionType: InventoryTransactionTypeEnum::PURCHASE->value,
            transactionDate: now(),
            serialId: $serial->id,
        ));
        $posting->stockOut(new InventoryMovementData(
            productId: $this->product->id,
            productVariantId: null,
            warehouseId: $this->warehouse->id,
            quantity: 1,
            unitCost: 50,
            transactionType: InventoryTransactionTypeEnum::SALE->value,
            transactionDate: now(),
            serialId: $serial->id,
        ));

        $this->assertSame(ProductSerialStatusEnum::SOLD, $serial->fresh()->status);

        $saleItem = $this->sale->items->first();
        $payload = [
            'sale_uuid' => $this->sale->uuid,
            'return_date' => now()->toDateString(),
            'items' => [[
                'sale_item_uuid' => $saleItem->uuid,
                'returned_quantity' => 1,
                'serials' => ['SERIAL-RETURN-001'],
            ]],
        ];

        $createResponse = $this->postJson(route('sales-returns.store'), $payload);
        $createResponse->assertCreated();
        $returnUuid = $createResponse->json('data.uuid');

        $this->postJson(route('sales-returns.confirm', ['uuid' => $returnUuid]))
            ->assertOk();

        app(TenantManager::class)->setTenant($this->tenant);

        $this->assertSame(
            ProductSerialStatusEnum::AVAILABLE,
            ProductSerial::query()->findOrFail($serial->id)->status
        );

        $secondReturn = $this->postJson(route('sales-returns.store'), $payload);
        $secondReturn->assertCreated();

        $this->postJson(route('sales-returns.confirm', [
            'uuid' => $secondReturn->json('data.uuid'),
        ]))->assertUnprocessable();

        $stock = ProductStock::query()
            ->where('product_id', $this->product->id)
            ->where('warehouse_id', $this->warehouse->id)
            ->firstOrFail();

        $this->assertEquals(6, (float) $stock->quantity);
    }

    public function test_batch_sales_return_requires_original_batch_and_posts_converted_base_quantity(): void
    {
        $baseUnit = Unit::create([
            'tenant_id' => $this->tenant->id,
            'code' => 'SR-BATCH-EACH',
            'name' => 'Sales Return Batch Each',
            'short_name' => 'ea',
            'is_active' => true,
        ]);

        $boxUnit = Unit::create([
            'tenant_id' => $this->tenant->id,
            'code' => 'SR-BATCH-BOX',
            'name' => 'Sales Return Batch Box',
            'short_name' => 'box',
            'is_active' => true,
        ]);

        $batchProduct = Product::create([
            'tenant_id' => $this->tenant->id,
            'unit_id' => $baseUnit->id,
            'code' => 'SR-BATCH-PRODUCT',
            'sku' => 'SR-BATCH-SKU',
            'name' => 'Sales Return Batch Product',
            'slug' => 'sales-return-batch-product',
            'product_type' => ProductTypeEnum::PRODUCT,
            'inventory_tracking_type' => InventoryTrackingTypeEnum::BATCH,
            'is_active' => true,
            'purchase_price' => 50,
            'selling_price' => 100,
        ]);

        ProductUnit::create([
            'product_id' => $batchProduct->id,
            'unit_id' => $baseUnit->id,
            'conversion_factor' => 1,
            'is_purchase_unit' => true,
            'is_sales_unit' => true,
            'is_active' => true,
        ]);

        ProductUnit::create([
            'product_id' => $batchProduct->id,
            'unit_id' => $boxUnit->id,
            'conversion_factor' => 10,
            'is_purchase_unit' => true,
            'is_sales_unit' => true,
            'is_active' => true,
        ]);

        $supplier = Supplier::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Sales Return Batch Supplier',
            'is_active' => true,
        ]);

        $purchaseService = app(PurchaseService::class);
        $purchase = $purchaseService->create([
            'supplier_uuid' => $supplier->uuid,
            'warehouse_uuid' => $this->warehouse->uuid,
            'purchase_date' => now()->toDateString(),
            'items' => [[
                'product_uuid' => $batchProduct->uuid,
                'unit_uuid' => $boxUnit->uuid,
                'quantity' => 2,
                'unit_cost' => 500,
                'batches' => [
                    ['batch_no' => 'SR-LOT-BOX-01', 'quantity' => 1],
                    ['batch_no' => 'SR-LOT-BOX-02', 'quantity' => 1],
                ],
            ]],
        ]);
        $purchaseService->confirm($purchase);

        $batch = ProductBatch::query()
            ->where('product_id', $batchProduct->id)
            ->where('batch_no', 'SR-LOT-BOX-01')
            ->firstOrFail();

        $saleService = app(SaleService::class);
        $sale = $saleService->create([
            'customer_uuid' => $this->customer->uuid,
            'warehouse_uuid' => $this->warehouse->uuid,
            'sale_date' => now()->toDateString(),
            'items' => [[
                'product_uuid' => $batchProduct->uuid,
                'unit_uuid' => $boxUnit->uuid,
                'quantity' => 2,
                'unit_price' => 1000,
                'batches' => [
                    ['batch_no' => 'SR-LOT-BOX-01', 'quantity' => 1],
                    ['batch_no' => 'SR-LOT-BOX-02', 'quantity' => 1],
                ],
            ]],
        ]);
        $sale = $saleService->confirm($sale->uuid);
        $saleItem = $sale->items->first();

        $this->assertEquals(20, (float) $saleItem->base_quantity);
        $this->assertDatabaseHas('sale_item_batches', [
            'sale_item_id' => $saleItem->id,
            'product_batch_id' => $batch->id,
            'quantity' => 1,
            'base_quantity' => 10,
        ]);

        $basePayload = [
            'sale_uuid' => $sale->uuid,
            'return_date' => now()->toDateString(),
            'items' => [[
                'sale_item_uuid' => $saleItem->uuid,
                'returned_quantity' => 0.75,
                'unit_price' => 1000,
            ]],
        ];

        $this->postJson(route('sales-returns.store'), $basePayload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors('items.0.batches');

        $unknownBatchPayload = $basePayload;
        $unknownBatchPayload['items'][0]['batches'] = [[
            'batch_no' => 'NOT-ON-THIS-SALE',
            'quantity' => 0.75,
        ]];

        $this->postJson(route('sales-returns.store'), $unknownBatchPayload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors('items.0.batches.0.batch_no');

        $validPayload = $basePayload;
        $validPayload['items'][0]['batches'] = [[
            'batch_no' => 'SR-LOT-BOX-01',
            'quantity' => 0.75,
        ]];

        $createResponse = $this->postJson(route('sales-returns.store'), $validPayload);
        $createResponse->assertCreated();
        $returnUuid = $createResponse->json('data.uuid');

        app(TenantManager::class)->setTenant($this->tenant);

        $return = SalesReturn::query()->where('uuid', $returnUuid)->firstOrFail();
        $returnItem = $return->items()->firstOrFail();

        $this->assertEquals(7.5, (float) $returnItem->base_quantity);
        $this->assertDatabaseHas('sale_return_item_batches', [
            'sales_return_item_id' => $returnItem->id,
            'product_batch_id' => $batch->id,
            'batch_no' => 'SR-LOT-BOX-01',
            'quantity' => 0.75,
        ]);

        $excessPayload = $validPayload;
        $excessPayload['items'][0]['returned_quantity'] = 0.5;
        $excessPayload['items'][0]['batches'][0]['quantity'] = 0.5;

        $this->postJson(route('sales-returns.store'), $excessPayload)
            ->assertUnprocessable();

        $this->postJson(route('sales-returns.confirm', ['uuid' => $returnUuid]))
            ->assertOk();

        app(TenantManager::class)->setTenant($this->tenant);

        $this->assertEquals(7.5, (float) $batch->fresh()->remaining_quantity);
        $this->assertDatabaseHas('stock_ledgers', [
            'product_id' => $batchProduct->id,
            'product_batch_id' => $batch->id,
            'quantity_in' => 7.5,
            'transaction_type' => 'SALE_RETURN',
        ]);
    }

    public function test_sales_return_can_be_cancelled(): void
    {
        $saleItem = $this->sale->items->first();
        $createRes = $this->postJson(route('sales-returns.store'), [
            'sale_uuid' => $this->sale->uuid,
            'return_date' => now()->toDateString(),
            'items' => [['sale_item_uuid' => $saleItem->uuid, 'returned_quantity' => 1, 'unit_price' => 100]],
        ]);

        $uuid = $createRes->json('data.uuid');
        $this->postJson(route('sales-returns.confirm', ['uuid' => $uuid]))->assertOk();

        $cancelRes = $this->postJson(route('sales-returns.cancel', ['uuid' => $uuid]), ['reason' => 'Customer change']);
        $cancelRes->assertOk()->assertJsonPath('data.status', 'cancelled');

        app(TenantManager::class)->setTenant($this->tenant);
        $stock = ProductStock::query()->where('product_id', $this->product->id)->where('warehouse_id', $this->warehouse->id)->first();
        $this->assertNotNull($stock);
        $this->assertEquals(5, (float) $stock->quantity);
    }

    public function test_cannot_modify_confirmed_sales_return(): void
    {
        $saleItem = $this->sale->items->first();
        $createRes = $this->postJson(route('sales-returns.store'), [
            'sale_uuid' => $this->sale->uuid,
            'return_date' => now()->toDateString(),
            'items' => [['sale_item_uuid' => $saleItem->uuid, 'returned_quantity' => 1, 'unit_price' => 100]],
        ]);

        $uuid = $createRes->json('data.uuid');
        $this->postJson(route('sales-returns.confirm', ['uuid' => $uuid]))->assertOk();

        $updateRes = $this->putJson(route('sales-returns.update', ['uuid' => $uuid]), [
            'items' => [['sale_item_uuid' => $saleItem->uuid, 'returned_quantity' => 2, 'unit_price' => 100]],
        ]);
        $updateRes->assertUnprocessable();
    }
}
