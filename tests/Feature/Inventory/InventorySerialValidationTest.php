<?php

namespace Tests\Feature\Inventory;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

use Tests\Support\CreatesTenant;
use Tests\Support\CreatesWarehouse;
use Tests\Support\CreatesProduct;

use App\Core\Enums\DocumentStatusEnum;
use App\Core\Exceptions\BusinessException;

use App\Modules\Settings\Models\Setting;

use App\Modules\Tenant\Models\Tenant;
use App\Modules\Product\Models\Product;
use App\Modules\Warehouse\Models\Warehouse;

use App\Modules\Accounting\Services\AccountingSetupService;

use App\Modules\Inventory\Enums\InventoryTransactionTypeEnum;
use App\Modules\Inventory\Enums\ProductSerialStatusEnum;

use App\Modules\Inventory\Models\ProductBatch;
use App\Modules\Inventory\Models\ProductSerial;
use App\Modules\Inventory\Models\ProductStock;

use App\Modules\OpeningStock\Models\OpeningStock;

use App\Modules\OpeningStock\Services\OpeningStockInventoryPostingService;

use App\Modules\Product\Enums\InventoryTrackingTypeEnum;

class InventorySerialValidationTest extends TestCase
{
    use RefreshDatabase;
    use CreatesTenant;
    use CreatesWarehouse;
    use CreatesProduct;

    protected Tenant $tenant;

    protected Product $product;

    protected Warehouse $warehouse;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = $this->createTestTenant();

        app(AccountingSetupService::class)
            ->setup($this->tenant);

        Setting::updateOrCreate(
            [
                'tenant_id' => $this->tenant->id,
                'group' => 'inventory',
                'key' => 'costing_method',
            ],
            [
                'value' => 'FIFO',
            ]
        );

        $this->warehouse = $this->createWarehouse();

        $this->product = $this->createProduct([
            'inventory_tracking_type' =>
                InventoryTrackingTypeEnum::SERIAL,
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | SERIAL VALIDATION
    |--------------------------------------------------------------------------
    */

    public function test_duplicate_serial_is_rejected(): void
    {
        $serial = $this->createSerial(
            serialNumber: 'SERIAL-DUP-001'
        );

        $firstOpeningStock = $this->createOpeningStock(
            documentNo: 'OS-SERIAL-DUP-001'
        );

        $this->addOpeningStockItem(
            openingStock: $firstOpeningStock,
            serial: $serial
        );

        $service = app(
            OpeningStockInventoryPostingService::class
        );

        /*
        |--------------------------------------------------------------------------
        | First posting
        |--------------------------------------------------------------------------
        */

        $service->post(
            $firstOpeningStock->fresh()
        );

        /*
        |--------------------------------------------------------------------------
        | Second Opening Stock
        |--------------------------------------------------------------------------
        */

        $secondOpeningStock = $this->createOpeningStock(
            documentNo: 'OS-SERIAL-DUP-002'
        );

        $this->addOpeningStockItem(
            openingStock: $secondOpeningStock,
            serial: $serial
        );

        /*
        |--------------------------------------------------------------------------
        | Duplicate serial must fail
        |--------------------------------------------------------------------------
        */

        $this->expectException(
            BusinessException::class
        );

        $this->expectExceptionMessage(
            'Serial number is already in stock.'
        );

        $service->post(
            $secondOpeningStock->fresh()
        );
    }

    public function test_duplicate_serial_uses_expected_error_code(): void
    {
        $serial = $this->createSerial(
            serialNumber: 'SERIAL-CODE-001'
        );

        $firstOpeningStock = $this->createOpeningStock(
            documentNo: 'OS-SERIAL-CODE-001'
        );

        $this->addOpeningStockItem(
            openingStock: $firstOpeningStock,
            serial: $serial
        );

        $service = app(
            OpeningStockInventoryPostingService::class
        );

        $service->post(
            $firstOpeningStock->fresh()
        );

        $secondOpeningStock = $this->createOpeningStock(
            documentNo: 'OS-SERIAL-CODE-002'
        );

        $this->addOpeningStockItem(
            openingStock: $secondOpeningStock,
            serial: $serial
        );

        try {
            $service->post(
                $secondOpeningStock->fresh()
            );

            $this->fail(
                'Expected duplicate serial validation to fail.'
            );
        } catch (BusinessException $exception) {
            $this->assertSame(
                'SERIAL_ALREADY_IN_STOCK',
                $exception->errorCode()
            );
        }
    }

    public function test_duplicate_serial_does_not_create_second_stock_ledger(): void
    {
        $serial = $this->createSerial(
            serialNumber: 'SERIAL-LEDGER-001'
        );

        $firstOpeningStock = $this->createOpeningStock(
            documentNo: 'OS-SERIAL-LEDGER-001'
        );

        $this->addOpeningStockItem(
            openingStock: $firstOpeningStock,
            serial: $serial
        );

        $service = app(
            OpeningStockInventoryPostingService::class
        );

        $service->post(
            $firstOpeningStock->fresh()
        );

        $secondOpeningStock = $this->createOpeningStock(
            documentNo: 'OS-SERIAL-LEDGER-002'
        );

        $this->addOpeningStockItem(
            openingStock: $secondOpeningStock,
            serial: $serial
        );

        try {
            $service->post(
                $secondOpeningStock->fresh()
            );
        } catch (BusinessException) {
            // Expected.
        }

        $this->assertDatabaseCount(
            'stock_ledgers',
            1
        );

        $this->assertDatabaseHas(
            'stock_ledgers',
            [
                'referenceable_type' =>
                    OpeningStock::class,

                'referenceable_id' =>
                    $firstOpeningStock->id,

                'product_serial_id' =>
                    $serial->id,

                'transaction_type' =>
                    InventoryTransactionTypeEnum::OPENING_STOCK->value,

                'quantity_in' =>
                    1,
            ]
        );

        $this->assertDatabaseMissing(
            'stock_ledgers',
            [
                'referenceable_type' =>
                    OpeningStock::class,

                'referenceable_id' =>
                    $secondOpeningStock->id,

                'product_serial_id' =>
                    $serial->id,
            ]
        );
    }

    public function test_duplicate_serial_does_not_increase_product_stock(): void
    {
        $serial = $this->createSerial(
            serialNumber: 'SERIAL-STOCK-001'
        );

        $firstOpeningStock = $this->createOpeningStock(
            documentNo: 'OS-SERIAL-STOCK-001'
        );

        $this->addOpeningStockItem(
            openingStock: $firstOpeningStock,
            serial: $serial
        );

        $service = app(
            OpeningStockInventoryPostingService::class
        );

        $service->post(
            $firstOpeningStock->fresh()
        );

        $stock = ProductStock::query()
            ->where(
                'product_id',
                $this->product->id
            )
            ->where(
                'warehouse_id',
                $this->warehouse->id
            )
            ->firstOrFail();

        $this->assertEquals(
            1,
            (float) $stock->quantity
        );

        $secondOpeningStock = $this->createOpeningStock(
            documentNo: 'OS-SERIAL-STOCK-002'
        );

        $this->addOpeningStockItem(
            openingStock: $secondOpeningStock,
            serial: $serial
        );

        try {
            $service->post(
                $secondOpeningStock->fresh()
            );
        } catch (BusinessException) {
            // Expected.
        }

        $stock->refresh();

        $this->assertEquals(
            1,
            (float) $stock->quantity
        );
    }

    public function test_reversed_serial_can_be_posted_again(): void
    {
        $serial = $this->createSerial(
            serialNumber: 'SERIAL-REPOST-001'
        );

        $firstOpeningStock = $this->createOpeningStock(
            documentNo: 'OS-SERIAL-REPOST-001'
        );

        $this->addOpeningStockItem(
            openingStock: $firstOpeningStock,
            serial: $serial
        );

        $service = app(
            OpeningStockInventoryPostingService::class
        );

        /*
        |--------------------------------------------------------------------------
        | First POST
        |--------------------------------------------------------------------------
        */

        $service->post(
            $firstOpeningStock->fresh()
        );

        /*
        |--------------------------------------------------------------------------
        | UNPOST
        |--------------------------------------------------------------------------
        */

        $service->unpost(
            $firstOpeningStock->fresh()
        );

        /*
        |--------------------------------------------------------------------------
        | Second POST using same serial
        |--------------------------------------------------------------------------
        */

        $secondOpeningStock = $this->createOpeningStock(
            documentNo: 'OS-SERIAL-REPOST-002'
        );

        $this->addOpeningStockItem(
            openingStock: $secondOpeningStock,
            serial: $serial
        );

        $service->post(
            $secondOpeningStock->fresh()
        );

        /*
        |--------------------------------------------------------------------------
        | Assertions
        |--------------------------------------------------------------------------
        */

        $secondOpeningStock->refresh();
        $serial->refresh();

        $this->assertSame(
            DocumentStatusEnum::CONFIRMED->value,
            $secondOpeningStock->status
        );

        $this->assertSame(
            ProductSerialStatusEnum::AVAILABLE,
            $serial->status
        );

        $this->assertDatabaseHas(
            'stock_ledgers',
            [
                'referenceable_type' =>
                    OpeningStock::class,

                'referenceable_id' =>
                    $secondOpeningStock->id,

                'product_serial_id' =>
                    $serial->id,

                'transaction_type' =>
                    InventoryTransactionTypeEnum::OPENING_STOCK->value,

                'quantity_in' =>
                    1,
            ]
        );
    }

    /*
    |--------------------------------------------------------------------------
    | SERIAL PRODUCT WITHOUT EXPLICIT SERIAL
    |--------------------------------------------------------------------------
    */

    public function test_serial_product_can_post_quantity_without_serial(): void
    {
        $openingStock = $this->createOpeningStock(
            documentNo: 'OS-SERIAL-QTY-005'
        );

        $this->addOpeningStockItem(
            openingStock: $openingStock,
            quantity: 5,
            unitCost: 100
        );

        $service = app(
            OpeningStockInventoryPostingService::class
        );

        $service->post(
            $openingStock->fresh()
        );

        $stock = ProductStock::query()
            ->where(
                'product_id',
                $this->product->id
            )
            ->where(
                'warehouse_id',
                $this->warehouse->id
            )
            ->firstOrFail();

        $this->assertEquals(
            5,
            (float) $stock->quantity
        );

        $this->assertDatabaseHas(
            'stock_ledgers',
            [
                'referenceable_type' =>
                    OpeningStock::class,

                'referenceable_id' =>
                    $openingStock->id,

                'product_id' =>
                    $this->product->id,

                'product_serial_id' =>
                    null,

                'quantity_in' =>
                    5,
            ]
        );
    }

    /*
    |--------------------------------------------------------------------------
    | TRACKING MODE VALIDATION
    |--------------------------------------------------------------------------
    */

    public function test_serial_product_cannot_use_batch(): void
    {
        $batch = $this->createBatch(
            batchNumber: 'BATCH-FOR-SERIAL-001'
        );

        $openingStock = $this->createOpeningStock(
            documentNo: 'OS-SERIAL-BATCH-001'
        );

        $this->addOpeningStockItem(
            openingStock: $openingStock,
            batch: $batch,
            quantity: 5
        );

        $service = app(
            OpeningStockInventoryPostingService::class
        );

        $this->expectException(
            BusinessException::class
        );

        $this->expectExceptionMessage(
            'Batch is not supported for serial-tracked products.'
        );

        $service->post(
            $openingStock->fresh()
        );
    }

    public function test_batch_product_cannot_use_serial(): void
    {
        $batchProduct = $this->createProduct([
            'inventory_tracking_type' =>
                InventoryTrackingTypeEnum::BATCH,
        ]);

        $serial = ProductSerial::create([
            'tenant_id' =>
                tenantId(),

            'product_id' =>
                $batchProduct->id,

            'warehouse_id' =>
                $this->warehouse->id,

            'serial_number' =>
                'SERIAL-FOR-BATCH-001',

            'purchase_cost' =>
                100,

            'status' =>
                ProductSerialStatusEnum::DRAFT,
        ]);

        $openingStock = $this->createOpeningStock(
            documentNo: 'OS-BATCH-SERIAL-001'
        );

        $this->addOpeningStockItem(
            openingStock: $openingStock,
            serial: $serial,
            product: $batchProduct,
            quantity: 1,
            unitCost: 100,
        );

        $service = app(
            OpeningStockInventoryPostingService::class
        );

        $this->expectException(
            BusinessException::class
        );

        $this->expectExceptionMessage(
            'Serial is not supported for batch-tracked products.'
        );

        $service->post(
            $openingStock->fresh()
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Helpers
    |--------------------------------------------------------------------------
    */

    protected function createSerial(
        string $serialNumber
    ): ProductSerial {
        return ProductSerial::create([
            'tenant_id' =>
                tenantId(),

            'product_id' =>
                $this->product->id,

            'warehouse_id' =>
                $this->warehouse->id,

            'serial_number' =>
                $serialNumber,

            'purchase_cost' =>
                100,

            'status' =>
                ProductSerialStatusEnum::DRAFT,
        ]);
    }

    protected function createBatch(
        string $batchNumber
    ): ProductBatch {
        return ProductBatch::create([
            'tenant_id' =>
                tenantId(),

            'product_id' =>
                $this->product->id,

            'warehouse_id' =>
                $this->warehouse->id,

            'batch_no' =>
                $batchNumber,
        ]);
    }

    protected function createOpeningStock(
        string $documentNo
    ): OpeningStock {
        return OpeningStock::create([
            'tenant_id' =>
                tenantId(),

            'document_no' =>
                $documentNo,

            'opening_date' =>
                now()->toDateString(),

            'status' =>
                DocumentStatusEnum::DRAFT->value,

            'warehouse_id' =>
                $this->warehouse->id,

            'total_quantity' =>
                1,

            'total_amount' =>
                100,
        ]);
    }

    protected function addOpeningStockItem(
        OpeningStock $openingStock,
        ?ProductSerial $serial = null,
        ?ProductBatch $batch = null,
        float $quantity = 1,
        float $unitCost = 100,
        ?Product $product = null
    ): void {
        $source = $openingStock->sources()->first();

        if (! $source) {
            $source = $openingStock->sources()->create([
                'tenant_id' =>
                    tenantId(),

                'supplier_id' =>
                    null,

                'bill_no' =>
                    'OS-BILL-' . $openingStock->document_no,

                'bill_date' =>
                    $openingStock->opening_date,

                'remarks' =>
                    'Opening stock serial validation test',
            ]);
        }

        $source->items()->create([
            'tenant_id' =>
                tenantId(),

            'product_id' =>
                $product?->id
                ?? $this->product->id,

            'product_serial_id' =>
                $serial?->id,

            'product_batch_id' =>
                $batch?->id,

            'quantity' =>
                $quantity,

            'unit_cost' =>
                $unitCost,

            'total_cost' =>
                $quantity * $unitCost,
        ]);
    }

}
