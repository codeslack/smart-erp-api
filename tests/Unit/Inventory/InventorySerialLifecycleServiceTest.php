<?php

namespace Tests\Unit\Inventory;

use App\Core\Exceptions\BusinessException;
use App\Modules\Inventory\Enums\ProductSerialStatusEnum;
use App\Modules\Inventory\Models\ProductSerial;
use App\Modules\Inventory\Repositories\Contracts\ProductSerialRepositoryInterface;
use App\Modules\Inventory\Services\Serial\InventorySerialLifecycleService;

use Mockery;

use Tests\TestCase;

class InventorySerialLifecycleServiceTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();

        parent::tearDown();
    }

    private function makeService(
        ?ProductSerialRepositoryInterface $repository = null,
    ): InventorySerialLifecycleService {
        return new InventorySerialLifecycleService(
            $repository ?? Mockery::mock(
                ProductSerialRepositoryInterface::class
            ),
        );
    }

    private function serial(
        ProductSerialStatusEnum $status,
    ): ProductSerial {
        $serial = new ProductSerial();

        $serial->id = 100;
        $serial->product_id = 10;
        $serial->warehouse_id = 20;
        $serial->serial_number = 'SN-001';
        $serial->status = $status;
        $serial->sold_at = now();

        return $serial;
    }

    public function test_it_activates_a_draft_serial(): void
    {
        $serial = $this->serial(
            ProductSerialStatusEnum::DRAFT
        );

        $updatedSerial = $this->serial(
            ProductSerialStatusEnum::AVAILABLE
        );

        $repository = Mockery::mock(
            ProductSerialRepositoryInterface::class
        );

        $repository
            ->shouldReceive('update')
            ->once()
            ->with($serial, [
                'status' => ProductSerialStatusEnum::AVAILABLE,
            ])
            ->andReturn($updatedSerial);

        $result = $this->makeService($repository)->activate($serial);

        $this->assertSame($updatedSerial, $result);
    }

    public function test_it_rejects_activating_a_non_draft_serial(): void
    {
        $serial = $this->serial(
            ProductSerialStatusEnum::AVAILABLE
        );

        $repository = Mockery::mock(
            ProductSerialRepositoryInterface::class
        );

        $repository->shouldNotReceive('update');

        $this->expectException(BusinessException::class);
        $this->expectExceptionMessage(
            'Only draft serials can be activated.'
        );

        $this->makeService($repository)->activate($serial);
    }

    public function test_it_deletes_a_draft_serial(): void
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

        $result = $this->makeService($repository)->deleteDraft($serial);

        $this->assertTrue($result);
    }

    public function test_it_returns_false_when_repository_delete_returns_false(): void
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
            ->andReturn(false);

        $result = $this->makeService($repository)->deleteDraft($serial);

        $this->assertFalse($result);
    }

    public function test_it_rejects_deleting_a_non_draft_serial(): void
    {
        $serial = $this->serial(
            ProductSerialStatusEnum::AVAILABLE
        );

        $repository = Mockery::mock(
            ProductSerialRepositoryInterface::class
        );

        $repository->shouldNotReceive('delete');

        $this->expectException(BusinessException::class);
        $this->expectExceptionMessage(
            'Only draft serials can be deleted.'
        );

        $this->makeService($repository)->deleteDraft($serial);
    }

    public function test_it_restores_an_available_serial_to_draft(): void
    {
        $serial = $this->serial(
            ProductSerialStatusEnum::AVAILABLE
        );

        $updatedSerial = $this->serial(
            ProductSerialStatusEnum::DRAFT
        );

        $repository = Mockery::mock(
            ProductSerialRepositoryInterface::class
        );

        $repository
            ->shouldReceive('update')
            ->once()
            ->with($serial, [
                'status' => ProductSerialStatusEnum::DRAFT,
                'sold_at' => null,
            ])
            ->andReturn($updatedSerial);

        $result = $this->makeService($repository)->restoreDraft($serial);

        $this->assertSame($updatedSerial, $result);
    }

    public function test_it_rejects_restoring_a_non_available_serial(): void
    {
        $serial = $this->serial(
            ProductSerialStatusEnum::SOLD
        );

        $repository = Mockery::mock(
            ProductSerialRepositoryInterface::class
        );

        $repository->shouldNotReceive('update');

        $this->expectException(BusinessException::class);
        $this->expectExceptionMessage(
            'Only available serial can be restored to draft.'
        );

        $this->makeService($repository)->restoreDraft($serial);
    }
}