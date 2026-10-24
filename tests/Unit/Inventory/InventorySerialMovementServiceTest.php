<?php

namespace Tests\Unit\Inventory;

use App\Core\Exceptions\BusinessException;
use App\Modules\Inventory\Enums\ProductSerialStatusEnum;
use App\Modules\Inventory\Models\ProductSerial;
use App\Modules\Inventory\Repositories\Contracts\ProductSerialRepositoryInterface;
use App\Modules\Inventory\Services\Serial\InventorySerialMovementService;

use Mockery;

use Tests\TestCase;

class InventorySerialMovementServiceTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();

        parent::tearDown();
    }

    private function makeService(
        ?ProductSerialRepositoryInterface $repository = null,
    ): InventorySerialMovementService {
        return new InventorySerialMovementService(
            $repository ?? Mockery::mock(
                ProductSerialRepositoryInterface::class
            ),
        );
    }

    private function serial(
        ProductSerialStatusEnum $status = ProductSerialStatusEnum::AVAILABLE,
    ): ProductSerial {
        $serial = new ProductSerial();

        $serial->id = 100;
        $serial->product_id = 10;
        $serial->warehouse_id = 20;
        $serial->product_variant_id = null;
        $serial->product_batch_id = null;
        $serial->serial_number = 'SN-001';
        $serial->status = $status;

        return $serial;
    }

    public function test_it_locks_serial_for_update(): void
    {
        $serial = $this->serial();

        $repository = Mockery::mock(
            ProductSerialRepositoryInterface::class
        );

        $repository
            ->shouldReceive('findForUpdate')
            ->once()
            ->with(100)
            ->andReturn($serial);

        $service = $this->makeService($repository);

        $this->assertSame($serial, $service->lock(100));
    }

    public function test_it_locks_available_serial_for_stock_in(): void
    {
        $serial = $this->serial();

        $repository = Mockery::mock(
            ProductSerialRepositoryInterface::class
        );

        $repository
            ->shouldReceive('findForUpdate')
            ->once()
            ->with(100)
            ->andReturn($serial);

        $service = $this->makeService($repository);

        $result = $service->lockForStockIn(
            serialId: 100,
            productId: 10,
            warehouseId: 20,
        );

        $this->assertSame($serial, $result);
    }

    public function test_it_locks_available_serial_for_stock_out(): void
    {
        $serial = $this->serial();

        $repository = Mockery::mock(
            ProductSerialRepositoryInterface::class
        );

        $repository
            ->shouldReceive('findForUpdate')
            ->once()
            ->with(100)
            ->andReturn($serial);

        $service = $this->makeService($repository);

        $result = $service->lockForStockOut(
            serialId: 100,
            productId: 10,
            warehouseId: 20,
        );

        $this->assertSame($serial, $result);
    }

    public function test_stock_in_validates_variant_and_batch(): void
    {
        $serial = $this->serial();
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

        $service = $this->makeService($repository);

        $result = $service->lockForStockIn(
            serialId: 100,
            productId: 10,
            warehouseId: 20,
            productVariantId: 30,
            productBatchId: 40,
        );

        $this->assertSame($serial, $result);
    }

    public function test_stock_in_rejects_wrong_product(): void
    {
        $this->expectException(BusinessException::class);
        $this->expectExceptionMessage(
            'Serial does not belong to the selected product.'
        );

        $repository = Mockery::mock(
            ProductSerialRepositoryInterface::class
        );

        $repository
            ->shouldReceive('findForUpdate')
            ->once()
            ->with(100)
            ->andReturn($this->serial());

        $this->makeService($repository)->lockForStockIn(
            serialId: 100,
            productId: 999,
            warehouseId: 20,
        );
    }

    public function test_stock_in_rejects_wrong_warehouse(): void
    {
        $this->expectException(BusinessException::class);
        $this->expectExceptionMessage(
            'Serial does not belong to the selected warehouse.'
        );

        $repository = Mockery::mock(
            ProductSerialRepositoryInterface::class
        );

        $repository
            ->shouldReceive('findForUpdate')
            ->once()
            ->with(100)
            ->andReturn($this->serial());

        $this->makeService($repository)->lockForStockIn(
            serialId: 100,
            productId: 10,
            warehouseId: 999,
        );
    }

    public function test_stock_in_rejects_wrong_variant(): void
    {
        $serial = $this->serial();
        $serial->product_variant_id = 30;

        $this->expectException(BusinessException::class);
        $this->expectExceptionMessage(
            'Serial does not belong to the selected product variant.'
        );

        $repository = Mockery::mock(
            ProductSerialRepositoryInterface::class
        );

        $repository
            ->shouldReceive('findForUpdate')
            ->once()
            ->with(100)
            ->andReturn($serial);

        $this->makeService($repository)->lockForStockIn(
            serialId: 100,
            productId: 10,
            warehouseId: 20,
            productVariantId: 999,
        );
    }

    public function test_stock_in_rejects_wrong_batch(): void
    {
        $serial = $this->serial();
        $serial->product_batch_id = 40;

        $this->expectException(BusinessException::class);
        $this->expectExceptionMessage(
            'Serial does not belong to the selected product batch.'
        );

        $repository = Mockery::mock(
            ProductSerialRepositoryInterface::class
        );

        $repository
            ->shouldReceive('findForUpdate')
            ->once()
            ->with(100)
            ->andReturn($serial);

        $this->makeService($repository)->lockForStockIn(
            serialId: 100,
            productId: 10,
            warehouseId: 20,
            productBatchId: 999,
        );
    }

    public function test_stock_in_rejects_non_available_serial(): void
    {
        $this->expectException(BusinessException::class);
        $this->expectExceptionMessage(
            'Only available serials can be used for stock-in.'
        );

        $repository = Mockery::mock(
            ProductSerialRepositoryInterface::class
        );

        $repository
            ->shouldReceive('findForUpdate')
            ->once()
            ->with(100)
            ->andReturn(
                $this->serial(ProductSerialStatusEnum::SOLD)
            );

        $this->makeService($repository)->lockForStockIn(
            serialId: 100,
            productId: 10,
            warehouseId: 20,
        );
    }

    public function test_stock_out_rejects_non_available_serial(): void
    {
        $this->expectException(BusinessException::class);
        $this->expectExceptionMessage(
            'Only available serials can be used for stock-out.'
        );

        $repository = Mockery::mock(
            ProductSerialRepositoryInterface::class
        );

        $repository
            ->shouldReceive('findForUpdate')
            ->once()
            ->with(100)
            ->andReturn(
                $this->serial(ProductSerialStatusEnum::SOLD)
            );

        $this->makeService($repository)->lockForStockOut(
            serialId: 100,
            productId: 10,
            warehouseId: 20,
        );
    }

    public function test_it_marks_available_serial_sold(): void
    {
        $serial = $this->serial();

        $repository = Mockery::mock(
            ProductSerialRepositoryInterface::class
        );

        $repository
            ->shouldReceive('markSold')
            ->once()
            ->with($serial);

        $service = $this->makeService($repository);

        $service->markSold($serial);

        $this->addToAssertionCount(1);
    }

    public function test_it_rejects_selling_non_available_serial(): void
    {
        $serial = $this->serial(
            ProductSerialStatusEnum::SOLD
        );

        $repository = Mockery::mock(
            ProductSerialRepositoryInterface::class
        );

        $repository->shouldNotReceive('markSold');

        $this->expectException(BusinessException::class);
        $this->expectExceptionMessage(
            'Only available serials can be sold.'
        );

        $this->makeService($repository)->markSold($serial);
    }

    public function test_it_makes_sold_serial_available(): void
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

        $service = $this->makeService($repository);

        $service->markAvailable($serial);

        $this->addToAssertionCount(1);
    }

    public function test_it_rejects_making_non_sold_serial_available(): void
    {
        $serial = $this->serial();

        $repository = Mockery::mock(
            ProductSerialRepositoryInterface::class
        );

        $repository->shouldNotReceive('markAvailable');

        $this->expectException(BusinessException::class);
        $this->expectExceptionMessage(
            'Only sold serials can be made available.'
        );

        $this->makeService($repository)->markAvailable($serial);
    }

    public function test_it_returns_sold_serial(): void
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

        $service = $this->makeService($repository);

        $service->markReturned($serial);

        $this->addToAssertionCount(1);
    }

    public function test_it_returns_damaged_serial(): void
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

        $service = $this->makeService($repository);

        $service->markReturned($serial);

        $this->addToAssertionCount(1);
    }

    public function test_it_rejects_returning_available_serial(): void
    {
        $serial = $this->serial();

        $repository = Mockery::mock(
            ProductSerialRepositoryInterface::class
        );

        $repository->shouldNotReceive('markReturned');

        $this->expectException(BusinessException::class);
        $this->expectExceptionMessage(
            'Only sold or damaged serials can be returned.'
        );

        $this->makeService($repository)->markReturned($serial);
    }
}