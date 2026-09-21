<?php

namespace Tests\Unit\Inventory;

use App\Core\Exceptions\BusinessException;

use App\Modules\Inventory\Enums\ProductSerialStatusEnum;
use App\Modules\Inventory\Models\ProductSerial;
use App\Modules\Inventory\Repositories\Contracts\ProductSerialRepositoryInterface;
use App\Modules\Inventory\Services\InventorySerialService;

use Illuminate\Support\Collection;

use Mockery;

use Tests\TestCase;

class InventorySerialServiceTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();

        parent::tearDown();
    }

    public function test_it_finds_serial_by_serial_number(): void
    {
        $serial = $this->serial(
            ProductSerialStatusEnum::AVAILABLE
        );

        $repository = Mockery::mock(
            ProductSerialRepositoryInterface::class
        );

        $repository
            ->shouldReceive('findBySerialNumber')
            ->once()
            ->with('SN-001')
            ->andReturn($serial);

        $service = new InventorySerialService($repository);

        $result = $service->findBySerialNumber(' SN-001 ');

        $this->assertSame($serial, $result);
    }

    public function test_it_finds_available_serials(): void
    {
        $serials = new Collection([
            $this->serial(
                ProductSerialStatusEnum::AVAILABLE
            ),
        ]);

        $repository = Mockery::mock(
            ProductSerialRepositoryInterface::class
        );

        $repository
            ->shouldReceive('findAvailable')
            ->once()
            ->with(
                10,
                20,
                30,
            )
            ->andReturn($serials);

        $service = new InventorySerialService($repository);

        $result = $service->findAvailable(
            productId: 10,
            warehouseId: 20,
            productVariantId: 30,
        );

        $this->assertSame($serials, $result);
    }

    public function test_it_locks_serial(): void
    {
        $serial = $this->serial(
            ProductSerialStatusEnum::AVAILABLE
        );

        $repository = Mockery::mock(
            ProductSerialRepositoryInterface::class
        );

        $repository
            ->shouldReceive('findForUpdate')
            ->once()
            ->with(100)
            ->andReturn($serial);

        $service = new InventorySerialService($repository);

        $result = $service->lock(100);

        $this->assertSame($serial, $result);
    }

    public function test_draft_serial_can_be_activated(): void
    {
        $serial = $this->serial(
            ProductSerialStatusEnum::DRAFT
        );

        $repository = Mockery::mock(
            ProductSerialRepositoryInterface::class
        );

        $repository
            ->shouldReceive('update')
            ->once()
            ->with(
                $serial,
                [
                    'status' =>
                        ProductSerialStatusEnum::AVAILABLE,
                ]
            )
            ->andReturn($serial);

        $service = new InventorySerialService($repository);

        $result = $service->activate($serial);

        $this->assertSame($serial, $result);
    }

    public function test_non_draft_serial_cannot_be_activated(): void
    {
        $serial = $this->serial(
            ProductSerialStatusEnum::AVAILABLE
        );

        $repository = Mockery::mock(
            ProductSerialRepositoryInterface::class
        );

        $repository->shouldNotReceive('update');

        $service = new InventorySerialService($repository);

        $this->expectException(BusinessException::class);

        $service->activate($serial);
    }

    public function test_draft_serial_can_be_deleted(): void
    {
        $serial = $this->serial(
            ProductSerialStatusEnum::DRAFT
        );

        $repository = Mockery::mock(
            ProductSerialRepositoryInterface::class
        );

        $repository
            ->shouldReceive('delete')
            ->once()
            ->with($serial)
            ->andReturn(true);

        $service = new InventorySerialService($repository);

        $result = $service->deleteDraft($serial);

        $this->assertTrue($result);
    }

    public function test_non_draft_serial_cannot_be_deleted(): void
    {
        $serial = $this->serial(
            ProductSerialStatusEnum::AVAILABLE
        );

        $repository = Mockery::mock(
            ProductSerialRepositoryInterface::class
        );

        $repository->shouldNotReceive('delete');

        $service = new InventorySerialService($repository);

        $this->expectException(BusinessException::class);

        $service->deleteDraft($serial);
    }

    public function test_available_serial_can_be_locked_for_stock_in(): void
    {
        $serial = $this->serial(
            ProductSerialStatusEnum::AVAILABLE
        );

        $serial->product_id = 10;
        $serial->warehouse_id = 20;
        $serial->product_variant_id = 30;
        $serial->product_batch_id = 40;

        $repository = Mockery::mock(
            ProductSerialRepositoryInterface::class
        );

        $repository
            ->shouldReceive('findForUpdate')
            ->once()
            ->with(100)
            ->andReturn($serial);

        $service = new InventorySerialService($repository);

        $result = $service->lockForStockIn(
            serialId: 100,
            productId: 10,
            warehouseId: 20,
            productVariantId: 30,
            productBatchId: 40,
        );

        $this->assertSame($serial, $result);
    }

    public function test_unavailable_serial_cannot_be_locked_for_stock_in(): void
    {
        $serial = $this->serial(
            ProductSerialStatusEnum::SOLD
        );

        $serial->product_id = 10;
        $serial->warehouse_id = 20;
        $serial->product_variant_id = null;
        $serial->product_batch_id = null;

        $repository = Mockery::mock(
            ProductSerialRepositoryInterface::class
        );

        $repository
            ->shouldReceive('findForUpdate')
            ->once()
            ->with(100)
            ->andReturn($serial);

        $service = new InventorySerialService($repository);

        $this->expectException(BusinessException::class);

        $service->lockForStockIn(
            serialId: 100,
            productId: 10,
            warehouseId: 20,
        );
    }

    public function test_available_serial_can_be_locked_for_stock_out(): void
    {
        $serial = $this->serial(
            ProductSerialStatusEnum::AVAILABLE
        );

        $serial->product_id = 10;
        $serial->warehouse_id = 20;
        $serial->product_variant_id = null;
        $serial->product_batch_id = null;

        $repository = Mockery::mock(
            ProductSerialRepositoryInterface::class
        );

        $repository
            ->shouldReceive('findForUpdate')
            ->once()
            ->with(100)
            ->andReturn($serial);

        $service = new InventorySerialService($repository);

        $result = $service->lockForStockOut(
            serialId: 100,
            productId: 10,
            warehouseId: 20,
        );

        $this->assertSame($serial, $result);
    }

    public function test_unavailable_serial_cannot_be_locked_for_stock_out(): void
    {
        $serial = $this->serial(
            ProductSerialStatusEnum::SOLD
        );

        $serial->product_id = 10;
        $serial->warehouse_id = 20;
        $serial->product_variant_id = null;
        $serial->product_batch_id = null;

        $repository = Mockery::mock(
            ProductSerialRepositoryInterface::class
        );

        $repository
            ->shouldReceive('findForUpdate')
            ->once()
            ->with(100)
            ->andReturn($serial);

        $service = new InventorySerialService($repository);

        $this->expectException(BusinessException::class);

        $service->lockForStockOut(
            serialId: 100,
            productId: 10,
            warehouseId: 20,
        );
    }

    public function test_invalid_product_cannot_lock_serial_for_stock_in(): void
    {
        $serial = $this->serial(
            ProductSerialStatusEnum::AVAILABLE
        );

        $serial->product_id = 10;
        $serial->warehouse_id = 20;
        $serial->product_variant_id = null;
        $serial->product_batch_id = null;

        $repository = Mockery::mock(
            ProductSerialRepositoryInterface::class
        );

        $repository
            ->shouldReceive('findForUpdate')
            ->once()
            ->with(100)
            ->andReturn($serial);

        $service = new InventorySerialService($repository);

        $this->expectException(BusinessException::class);

        $service->lockForStockIn(
            serialId: 100,
            productId: 999,
            warehouseId: 20,
        );
    }

    public function test_invalid_warehouse_cannot_lock_serial_for_stock_in(): void
    {
        $serial = $this->serial(
            ProductSerialStatusEnum::AVAILABLE
        );

        $serial->product_id = 10;
        $serial->warehouse_id = 20;
        $serial->product_variant_id = null;
        $serial->product_batch_id = null;

        $repository = Mockery::mock(
            ProductSerialRepositoryInterface::class
        );

        $repository
            ->shouldReceive('findForUpdate')
            ->once()
            ->with(100)
            ->andReturn($serial);

        $service = new InventorySerialService($repository);

        $this->expectException(BusinessException::class);

        $service->lockForStockIn(
            serialId: 100,
            productId: 10,
            warehouseId: 999,
        );
    }

    public function test_invalid_variant_cannot_lock_serial_for_stock_in(): void
    {
        $serial = $this->serial(
            ProductSerialStatusEnum::AVAILABLE
        );

        $serial->product_id = 10;
        $serial->warehouse_id = 20;
        $serial->product_variant_id = 30;
        $serial->product_batch_id = null;

        $repository = Mockery::mock(
            ProductSerialRepositoryInterface::class
        );

        $repository
            ->shouldReceive('findForUpdate')
            ->once()
            ->with(100)
            ->andReturn($serial);

        $service = new InventorySerialService($repository);

        $this->expectException(BusinessException::class);

        $service->lockForStockIn(
            serialId: 100,
            productId: 10,
            warehouseId: 20,
            productVariantId: 999,
        );
    }

    public function test_invalid_batch_cannot_lock_serial_for_stock_in(): void
    {
        $serial = $this->serial(
            ProductSerialStatusEnum::AVAILABLE
        );

        $serial->product_id = 10;
        $serial->warehouse_id = 20;
        $serial->product_variant_id = null;
        $serial->product_batch_id = 40;

        $repository = Mockery::mock(
            ProductSerialRepositoryInterface::class
        );

        $repository
            ->shouldReceive('findForUpdate')
            ->once()
            ->with(100)
            ->andReturn($serial);

        $service = new InventorySerialService($repository);

        $this->expectException(BusinessException::class);

        $service->lockForStockIn(
            serialId: 100,
            productId: 10,
            warehouseId: 20,
            productBatchId: 999,
        );
    }

    public function test_it_registers_available_serial(): void
    {
        $serial = $this->serial(
            ProductSerialStatusEnum::AVAILABLE
        );

        $repository = Mockery::mock(
            ProductSerialRepositoryInterface::class
        );

        $repository
            ->shouldReceive('findBySerialNumber')
            ->once()
            ->with('SN-002')
            ->andReturnNull();

        $repository
            ->shouldReceive('register')
            ->once()
            ->with(Mockery::on(
                function (array $data): bool {
                    return $data['product_id'] === 10
                        && $data['warehouse_id'] === 20
                        && $data['product_variant_id'] === 30
                        && $data['product_batch_id'] === 40
                        && $data['serial_number'] === 'SN-002'
                        && $data['imei_number'] === 'IMEI-002'
                        && $data['purchase_cost'] === 1250.50
                        && $data['status']
                            === ProductSerialStatusEnum::AVAILABLE;
                }
            ))
            ->andReturn($serial);

        $service = new InventorySerialService($repository);

        $result = $service->register(
            productId: 10,
            warehouseId: 20,
            serialNumber: ' SN-002 ',
            purchaseCost: 1250.50,
            productVariantId: 30,
            productBatchId: 40,
            imeiNumber: 'IMEI-002',
        );

        $this->assertSame($serial, $result);
    }

    public function test_it_registers_draft_serial(): void
    {
        $serial = $this->serial(
            ProductSerialStatusEnum::DRAFT
        );

        $repository = Mockery::mock(
            ProductSerialRepositoryInterface::class
        );

        $repository
            ->shouldReceive('findBySerialNumber')
            ->once()
            ->with('SN-DRAFT')
            ->andReturnNull();

        $repository
            ->shouldReceive('register')
            ->once()
            ->with(Mockery::on(
                function (array $data): bool {
                    $this->assertSame(
                        10,
                        $data['product_id']
                    );

                    $this->assertNull(
                        $data['product_variant_id']
                    );

                    $this->assertSame(
                        20,
                        $data['warehouse_id']
                    );

                    $this->assertNull(
                        $data['product_batch_id']
                    );

                    $this->assertSame(
                        'SN-DRAFT',
                        $data['serial_number']
                    );

                    $this->assertNull(
                        $data['imei_number']
                    );

                    $this->assertSame(
                        500.0,
                        $data['purchase_cost']
                    );

                    $this->assertNull(
                        $data['warranty_expiry']
                    );

                    $this->assertSame(
                        ProductSerialStatusEnum::DRAFT,
                        $data['status']
                    );

                    $this->assertNull(
                        $data['sourceable_type']
                    );

                    $this->assertNull(
                        $data['sourceable_id']
                    );

                    $this->assertNull(
                        $data['remarks']
                    );

                    return true;
                }
            ))
            ->andReturn($serial);

        $service = new InventorySerialService($repository);

        $result = $service->registerDraft(
            productId: 10,
            warehouseId: 20,
            serialNumber: ' SN-DRAFT ',
            purchaseCost: 500,
        );

        $this->assertSame($serial, $result);
    }

    public function test_empty_serial_number_cannot_be_registered(): void
    {
        $repository = Mockery::mock(
            ProductSerialRepositoryInterface::class
        );

        $repository
            ->shouldNotReceive('findBySerialNumber');

        $repository
            ->shouldNotReceive('register');

        $service = new InventorySerialService($repository);

        $this->expectException(BusinessException::class);

        $service->register(
            productId: 10,
            warehouseId: 20,
            serialNumber: '   ',
            purchaseCost: 1000,
        );
    }

    public function test_negative_purchase_cost_cannot_be_registered(): void
    {
        $repository = Mockery::mock(
            ProductSerialRepositoryInterface::class
        );

        $repository
            ->shouldNotReceive('findBySerialNumber');

        $repository
            ->shouldNotReceive('register');

        $service = new InventorySerialService($repository);

        $this->expectException(BusinessException::class);

        $service->register(
            productId: 10,
            warehouseId: 20,
            serialNumber: 'SN-003',
            purchaseCost: -1,
        );
    }

    public function test_duplicate_serial_number_cannot_be_registered(): void
    {
        $existing = $this->serial(
            ProductSerialStatusEnum::AVAILABLE
        );

        $repository = Mockery::mock(
            ProductSerialRepositoryInterface::class
        );

        $repository
            ->shouldReceive('findBySerialNumber')
            ->once()
            ->with('SN-001')
            ->andReturn($existing);

        $repository
            ->shouldNotReceive('register');

        $service = new InventorySerialService($repository);

        $this->expectException(BusinessException::class);

        $service->register(
            productId: 10,
            warehouseId: 20,
            serialNumber: 'SN-001',
            purchaseCost: 1000,
        );
    }

    public function test_available_serial_can_be_restored_to_draft(): void
    {
        $serial = $this->serial(
            ProductSerialStatusEnum::AVAILABLE
        );

        $repository = Mockery::mock(
            ProductSerialRepositoryInterface::class
        );

        $repository
            ->shouldReceive('update')
            ->once()
            ->with(
                $serial,
                [
                    'status' =>
                        ProductSerialStatusEnum::DRAFT,
                    'sold_at' => null,
                ]
            )
            ->andReturn($serial);

        $service = new InventorySerialService($repository);

        $result = $service->restoreDraft($serial);

        $this->assertSame($serial, $result);
    }

    public function test_non_available_serial_cannot_be_restored_to_draft(): void
    {
        $serial = $this->serial(
            ProductSerialStatusEnum::SOLD
        );

        $repository = Mockery::mock(
            ProductSerialRepositoryInterface::class
        );

        $repository->shouldNotReceive('update');

        $service = new InventorySerialService($repository);

        $this->expectException(BusinessException::class);

        $service->restoreDraft($serial);
    }

    public function test_available_serial_can_be_marked_sold(): void
    {
        $serial = $this->serial(
            ProductSerialStatusEnum::AVAILABLE
        );

        $repository = Mockery::mock(
            ProductSerialRepositoryInterface::class
        );

        $repository
            ->shouldReceive('markSold')
            ->once()
            ->with($serial);

        $service = new InventorySerialService($repository);

        $service->markSold($serial);

        $this->addToAssertionCount(1);
    }

    public function test_non_available_serial_cannot_be_marked_sold(): void
    {
        $serial = $this->serial(
            ProductSerialStatusEnum::SOLD
        );

        $repository = Mockery::mock(
            ProductSerialRepositoryInterface::class
        );

        $repository->shouldNotReceive('markSold');

        $service = new InventorySerialService($repository);

        $this->expectException(BusinessException::class);

        $service->markSold($serial);
    }

    public function test_sold_serial_can_be_made_available(): void
    {
        $serial = $this->serial(
            ProductSerialStatusEnum::SOLD
        );

        $repository = Mockery::mock(
            ProductSerialRepositoryInterface::class
        );

        $repository
            ->shouldReceive('markAvailable')
            ->once()
            ->with($serial);

        $service = new InventorySerialService($repository);

        $service->markAvailable($serial);

        $this->addToAssertionCount(1);
    }

    public function test_non_sold_serial_cannot_be_made_available(): void
    {
        $serial = $this->serial(
            ProductSerialStatusEnum::AVAILABLE
        );

        $repository = Mockery::mock(
            ProductSerialRepositoryInterface::class
        );

        $repository->shouldNotReceive('markAvailable');

        $service = new InventorySerialService($repository);

        $this->expectException(BusinessException::class);

        $service->markAvailable($serial);
    }

    public function test_sold_serial_can_be_returned(): void
    {
        $serial = $this->serial(
            ProductSerialStatusEnum::SOLD
        );

        $repository = Mockery::mock(
            ProductSerialRepositoryInterface::class
        );

        $repository
            ->shouldReceive('markReturned')
            ->once()
            ->with($serial);

        $service = new InventorySerialService($repository);

        $service->markReturned($serial);

        $this->addToAssertionCount(1);
    }

    public function test_damaged_serial_can_be_returned(): void
    {
        $serial = $this->serial(
            ProductSerialStatusEnum::DAMAGED
        );

        $repository = Mockery::mock(
            ProductSerialRepositoryInterface::class
        );

        $repository
            ->shouldReceive('markReturned')
            ->once()
            ->with($serial);

        $service = new InventorySerialService($repository);

        $service->markReturned($serial);

        $this->addToAssertionCount(1);
    }

    public function test_available_serial_cannot_be_returned(): void
    {
        $serial = $this->serial(
            ProductSerialStatusEnum::AVAILABLE
        );

        $repository = Mockery::mock(
            ProductSerialRepositoryInterface::class
        );

        $repository->shouldNotReceive('markReturned');

        $service = new InventorySerialService($repository);

        $this->expectException(BusinessException::class);

        $service->markReturned($serial);
    }

    private function serial(
        ProductSerialStatusEnum $status
    ): ProductSerial {
        $serial = new ProductSerial();

        $serial->status = $status;
        $serial->serial_number = 'SN-001';

        return $serial;
    }
}