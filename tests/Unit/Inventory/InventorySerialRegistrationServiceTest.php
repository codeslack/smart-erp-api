<?php

namespace Tests\Unit\Inventory;

use App\Core\Exceptions\BusinessException;
use App\Modules\Inventory\Enums\ProductSerialStatusEnum;
use App\Modules\Inventory\Models\ProductSerial;
use App\Modules\Inventory\Repositories\Contracts\ProductSerialRepositoryInterface;
use App\Modules\Inventory\Services\Serial\InventorySerialRegistrationService;

use DateTimeImmutable;

use Mockery;

use Tests\TestCase;

class InventorySerialRegistrationServiceTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();

        parent::tearDown();
    }

    private function makeService(
        ?ProductSerialRepositoryInterface $repository = null,
    ): InventorySerialRegistrationService {
        return new InventorySerialRegistrationService(
            $repository ?? Mockery::mock(
                ProductSerialRepositoryInterface::class
            ),
        );
    }

    private function serial(
        ProductSerialStatusEnum $status = ProductSerialStatusEnum::AVAILABLE,
    ): ProductSerial {
        $serial = new ProductSerial();

        $serial->status = $status;
        $serial->serial_number = 'SN-001';

        return $serial;
    }

    public function test_it_registers_available_serial(): void
    {
        $serial = $this->serial();

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
                fn (array $data): bool =>
                    $data['product_id'] === 10
                    && $data['product_variant_id'] === 30
                    && $data['warehouse_id'] === 20
                    && $data['product_batch_id'] === 40
                    && $data['serial_number'] === 'SN-002'
                    && $data['imei_number'] === 'IMEI-002'
                    && $data['purchase_cost'] === 1250.50
                    && $data['status'] === ProductSerialStatusEnum::AVAILABLE
            ))
            ->andReturn($serial);

        $service = $this->makeService($repository);

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
                    $this->assertSame(10, $data['product_id']);
                    $this->assertNull($data['product_variant_id']);
                    $this->assertSame(20, $data['warehouse_id']);
                    $this->assertNull($data['product_batch_id']);
                    $this->assertSame('SN-DRAFT', $data['serial_number']);
                    $this->assertNull($data['imei_number']);
                    $this->assertSame(500.0, $data['purchase_cost']);
                    $this->assertNull($data['warranty_expiry']);
                    $this->assertSame(
                        ProductSerialStatusEnum::DRAFT,
                        $data['status']
                    );
                    $this->assertNull($data['sourceable_type']);
                    $this->assertNull($data['sourceable_id']);
                    $this->assertNull($data['remarks']);

                    return true;
                }
            ))
            ->andReturn($serial);

        $service = $this->makeService($repository);

        $result = $service->registerDraft(
            productId: 10,
            warehouseId: 20,
            serialNumber: ' SN-DRAFT ',
            purchaseCost: 500,
        );

        $this->assertSame($serial, $result);
    }

    public function test_it_registers_serial_with_optional_fields(): void
    {
        $serial = $this->serial();
        $warrantyExpiry = new DateTimeImmutable('2028-12-31');

        $repository = Mockery::mock(
            ProductSerialRepositoryInterface::class
        );

        $repository
            ->shouldReceive('findBySerialNumber')
            ->once()
            ->with('SN-OPTIONAL')
            ->andReturnNull();

        $repository
            ->shouldReceive('register')
            ->once()
            ->with(Mockery::on(
                function (array $data) use ($warrantyExpiry): bool {
                    $this->assertSame(
                        $warrantyExpiry,
                        $data['warranty_expiry']
                    );

                    $this->assertSame(
                        'App\\Modules\\Purchase\\Models\\PurchaseBill',
                        $data['sourceable_type']
                    );

                    $this->assertSame(55, $data['sourceable_id']);
                    $this->assertSame('New device', $data['remarks']);

                    return true;
                }
            ))
            ->andReturn($serial);

        $service = $this->makeService($repository);

        $result = $service->register(
            productId: 10,
            warehouseId: 20,
            serialNumber: 'SN-OPTIONAL',
            purchaseCost: 2500,
            warrantyExpiry: $warrantyExpiry,
            sourceableType: 'App\\Modules\\Purchase\\Models\\PurchaseBill',
            sourceableId: 55,
            remarks: 'New device',
        );

        $this->assertSame($serial, $result);
    }

    public function test_empty_serial_number_cannot_be_registered(): void
    {
        $repository = Mockery::mock(
            ProductSerialRepositoryInterface::class
        );

        $repository->shouldNotReceive('findBySerialNumber');
        $repository->shouldNotReceive('register');

        $service = $this->makeService($repository);

        $this->expectException(BusinessException::class);
        $this->expectExceptionMessage('Serial number is required.');

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

        $repository->shouldNotReceive('findBySerialNumber');
        $repository->shouldNotReceive('register');

        $service = $this->makeService($repository);

        $this->expectException(BusinessException::class);
        $this->expectExceptionMessage(
            'Serial purchase cost cannot be negative.'
        );

        $service->register(
            productId: 10,
            warehouseId: 20,
            serialNumber: 'SN-003',
            purchaseCost: -1,
        );
    }

    public function test_duplicate_serial_number_cannot_be_registered(): void
    {
        $existing = $this->serial();

        $repository = Mockery::mock(
            ProductSerialRepositoryInterface::class
        );

        $repository
            ->shouldReceive('findBySerialNumber')
            ->once()
            ->with('SN-001')
            ->andReturn($existing);

        $repository->shouldNotReceive('register');

        $service = $this->makeService($repository);

        $this->expectException(BusinessException::class);
        $this->expectExceptionMessage(
            'Serial number already exists.'
        );

        $service->register(
            productId: 10,
            warehouseId: 20,
            serialNumber: 'SN-001',
            purchaseCost: 1000,
        );
    }

    public function test_zero_purchase_cost_is_allowed(): void
    {
        $serial = $this->serial();

        $repository = Mockery::mock(
            ProductSerialRepositoryInterface::class
        );

        $repository
            ->shouldReceive('findBySerialNumber')
            ->once()
            ->with('SN-FREE')
            ->andReturnNull();

        $repository
            ->shouldReceive('register')
            ->once()
            ->with(Mockery::on(
                fn (array $data): bool =>
                    $data['purchase_cost'] === 0.0
                    && $data['status'] === ProductSerialStatusEnum::AVAILABLE
            ))
            ->andReturn($serial);

        $service = $this->makeService($repository);

        $result = $service->register(
            productId: 10,
            warehouseId: 20,
            serialNumber: 'SN-FREE',
            purchaseCost: 0,
        );

        $this->assertSame($serial, $result);
    }
}