<?php

namespace Tests\Unit\Inventory;

use App\Modules\Inventory\Enums\ProductSerialStatusEnum;
use App\Modules\Inventory\Models\ProductSerial;
use App\Modules\Inventory\Repositories\Contracts\ProductSerialRepositoryInterface;
use App\Modules\Inventory\Services\InventorySerialService;
use App\Modules\Inventory\Services\Serial\InventorySerialLifecycleService;
use App\Modules\Inventory\Services\Serial\InventorySerialMovementService;
use App\Modules\Inventory\Services\Serial\InventorySerialRegistrationService;

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

    private function makeService(
        ?ProductSerialRepositoryInterface $repository = null,
        ?InventorySerialRegistrationService $registration = null,
        ?InventorySerialMovementService $movement = null,
        ?InventorySerialLifecycleService $lifecycle = null,
    ): InventorySerialService {
        return new InventorySerialService(
            $repository ?? Mockery::mock(
                ProductSerialRepositoryInterface::class
            ),
            $registration ?? Mockery::mock(
                InventorySerialRegistrationService::class
            ),
            $movement ?? Mockery::mock(
                InventorySerialMovementService::class
            ),
            $lifecycle ?? Mockery::mock(
                InventorySerialLifecycleService::class
            ),
        );
    }

    private function serial(): ProductSerial
    {
        $serial = new ProductSerial();

        $serial->status = ProductSerialStatusEnum::AVAILABLE;
        $serial->serial_number = 'SN-001';

        return $serial;
    }

    public function test_it_finds_serial_by_trimmed_serial_number(): void
    {
        $serial = $this->serial();

        $repository = Mockery::mock(
            ProductSerialRepositoryInterface::class
        );

        $repository
            ->shouldReceive('findBySerialNumber')
            ->once()
            ->with('SN-001')
            ->andReturn($serial);

        $service = $this->makeService(
            repository: $repository,
        );

        $this->assertSame(
            $serial,
            $service->findBySerialNumber(' SN-001 ')
        );
    }

    public function test_it_returns_null_when_serial_is_not_found(): void
    {
        $repository = Mockery::mock(
            ProductSerialRepositoryInterface::class
        );

        $repository
            ->shouldReceive('findBySerialNumber')
            ->once()
            ->with('MISSING')
            ->andReturnNull();

        $service = $this->makeService(
            repository: $repository,
        );

        $this->assertNull(
            $service->findBySerialNumber('MISSING')
        );
    }

    public function test_it_finds_available_serials(): void
    {
        $serials = new Collection([
            $this->serial(),
        ]);

        $repository = Mockery::mock(
            ProductSerialRepositoryInterface::class
        );

        $repository
            ->shouldReceive('findAvailable')
            ->once()
            ->with(10, 20, 30)
            ->andReturn($serials);

        $service = $this->makeService(
            repository: $repository,
        );

        $result = $service->findAvailable(
            10,
            20,
            30,
        );

        $this->assertSame($serials, $result);
    }

    public function test_it_delegates_lock_to_movement_service(): void
    {
        $serial = $this->serial();

        $movement = Mockery::mock(
            InventorySerialMovementService::class
        );

        $movement
            ->shouldReceive('lock')
            ->once()
            ->with(100)
            ->andReturn($serial);

        $service = $this->makeService(
            movement: $movement,
        );

        $this->assertSame(
            $serial,
            $service->lock(100)
        );
    }

    public function test_it_delegates_stock_in_locking(): void
    {
        $serial = $this->serial();

        $movement = Mockery::mock(
            InventorySerialMovementService::class
        );

        $movement
            ->shouldReceive('lockForStockIn')
            ->once()
            ->with(100, 10, 20, 30, 40)
            ->andReturn($serial);

        $service = $this->makeService(
            movement: $movement,
        );

        $result = $service->lockForStockIn(
            100,
            10,
            20,
            30,
            40,
        );

        $this->assertSame($serial, $result);
    }

    public function test_it_delegates_stock_out_locking(): void
    {
        $serial = $this->serial();

        $movement = Mockery::mock(
            InventorySerialMovementService::class
        );

        $movement
            ->shouldReceive('lockForStockOut')
            ->once()
            ->with(100, 10, 20, 30, 40)
            ->andReturn($serial);

        $service = $this->makeService(
            movement: $movement,
        );

        $result = $service->lockForStockOut(
            100,
            10,
            20,
            30,
            40,
        );

        $this->assertSame($serial, $result);
    }

    public function test_it_delegates_serial_registration(): void
    {
        $serial = $this->serial();

        $registration = Mockery::mock(
            InventorySerialRegistrationService::class
        );

        $registration
            ->shouldReceive('register')
            ->once()
            ->with(
                10,
                20,
                'SN-002',
                1250.50,
                30,
                40,
                'IMEI-002',
                null,
                null,
                null,
                null,
            )
            ->andReturn($serial);

        $service = $this->makeService(
            registration: $registration,
        );

        $result = $service->register(
            10,
            20,
            'SN-002',
            1250.50,
            30,
            40,
            'IMEI-002',
        );

        $this->assertSame($serial, $result);
    }

    public function test_it_delegates_draft_serial_registration(): void
    {
        $serial = $this->serial();

        $registration = Mockery::mock(
            InventorySerialRegistrationService::class
        );

        $registration
            ->shouldReceive('registerDraft')
            ->once()
            ->with(
                10,
                20,
                'SN-DRAFT',
                500.0,
                null,
                null,
                null,
                null,
                null,
                null,
                null,
            )
            ->andReturn($serial);

        $service = $this->makeService(
            registration: $registration,
        );

        $result = $service->registerDraft(
            10,
            20,
            'SN-DRAFT',
            500,
        );

        $this->assertSame($serial, $result);
    }

    public function test_it_delegates_activation_to_lifecycle_service(): void
    {
        $serial = $this->serial();

        $lifecycle = Mockery::mock(
            InventorySerialLifecycleService::class
        );

        $lifecycle
            ->shouldReceive('activate')
            ->once()
            ->with($serial)
            ->andReturn($serial);

        $service = $this->makeService(
            lifecycle: $lifecycle,
        );

        $this->assertSame(
            $serial,
            $service->activate($serial)
        );
    }

    public function test_it_delegates_draft_deletion(): void
    {
        $serial = $this->serial();

        $lifecycle = Mockery::mock(
            InventorySerialLifecycleService::class
        );

        $lifecycle
            ->shouldReceive('deleteDraft')
            ->once()
            ->with($serial)
            ->andReturn(true);

        $service = $this->makeService(
            lifecycle: $lifecycle,
        );

        $this->assertTrue(
            $service->deleteDraft($serial)
        );
    }

    public function test_it_delegates_draft_restoration(): void
    {
        $serial = $this->serial();

        $lifecycle = Mockery::mock(
            InventorySerialLifecycleService::class
        );

        $lifecycle
            ->shouldReceive('restoreDraft')
            ->once()
            ->with($serial)
            ->andReturn($serial);

        $service = $this->makeService(
            lifecycle: $lifecycle,
        );

        $this->assertSame(
            $serial,
            $service->restoreDraft($serial)
        );
    }

    public function test_it_delegates_mark_sold(): void
    {
        $serial = $this->serial();

        $movement = Mockery::mock(
            InventorySerialMovementService::class
        );

        $movement
            ->shouldReceive('markSold')
            ->once()
            ->with($serial);

        $service = $this->makeService(
            movement: $movement,
        );

        $service->markSold($serial);

        $this->addToAssertionCount(1);
    }

    public function test_it_delegates_mark_available(): void
    {
        $serial = $this->serial();

        $movement = Mockery::mock(
            InventorySerialMovementService::class
        );

        $movement
            ->shouldReceive('markAvailable')
            ->once()
            ->with($serial);

        $service = $this->makeService(
            movement: $movement,
        );

        $service->markAvailable($serial);

        $this->addToAssertionCount(1);
    }

    public function test_it_delegates_mark_returned(): void
    {
        $serial = $this->serial();

        $movement = Mockery::mock(
            InventorySerialMovementService::class
        );

        $movement
            ->shouldReceive('markReturned')
            ->once()
            ->with($serial);

        $service = $this->makeService(
            movement: $movement,
        );

        $service->markReturned($serial);

        $this->addToAssertionCount(1);
    }
}