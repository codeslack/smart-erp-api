<?php

namespace Tests\Unit\Modules\Inventory;

use Tests\TestCase;

use Illuminate\Database\QueryException;

use App\Modules\Inventory\Services\InventoryBatchService;
use App\Modules\Inventory\Models\ProductBatch;
use App\Modules\Inventory\Enums\ProductBatchStatusEnum;
use App\Core\Exceptions\BusinessException;
use App\Modules\Inventory\Repositories\Contracts\ProductBatchRepositoryInterface;

class InventoryBatchServiceTest extends TestCase
{
    public function test_it_recognizes_the_product_batch_unique_constraint_violation(): void
    {
        $service = new InventoryBatchService(
            $this->createMock(ProductBatchRepositoryInterface::class)
        );

        $exception = new QueryException(
            'mysql',
            'insert into product_batches (...) values (...)',
            [],
            new \PDOException(
                "SQLSTATE[23000]: Integrity constraint violation: "
                . "1062 Duplicate entry 'BATCH-001' for key "
                . "'product_batches.product_batches_unique_batch'",
                1062
            )
        );

        $this->assertTrue(
            $service->isDuplicateBatchException($exception)
        );
    }

    public function test_it_does_not_treat_an_unrelated_database_error_as_a_batch_duplicate(): void
    {
        $service = new InventoryBatchService(
            $this->createMock(ProductBatchRepositoryInterface::class)
        );

        $exception = new QueryException(
            'mysql',
            'insert into product_batches (...) values (...)',
            [],
            new \PDOException(
                'SQLSTATE[23000]: Integrity constraint violation: '
                . '1062 Duplicate entry for key unrelated_unique_index',
                1062
            )
        );

        $this->assertFalse(
            $service->isDuplicateBatchException($exception)
        );
    }

    public function test_inactive_batch_cannot_receive_stock(): void
    {
        $service = new InventoryBatchService(
            $this->createMock(ProductBatchRepositoryInterface::class)
        );

        $batch = new ProductBatch();
        $batch->forceFill([
            'status' => ProductBatchStatusEnum::DRAFT,
        ]);

        $this->expectException(BusinessException::class);
        $this->expectExceptionMessage(
            'Only active batches can receive or issue inventory.'
        );

        $service->increase($batch, 1);
    }

    public function test_inactive_batch_cannot_issue_stock(): void
    {
        $service = new InventoryBatchService(
            $this->createMock(ProductBatchRepositoryInterface::class)
        );

        $batch = new ProductBatch();
        $batch->forceFill([
            'status' => ProductBatchStatusEnum::DRAFT,
            'remaining_quantity' => 5,
        ]);

        $this->expectException(BusinessException::class);
        $this->expectExceptionMessage(
            'Only active batches can receive or issue inventory.'
        );

        $service->decrease($batch, 1);
    }


}