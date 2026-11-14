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



    public function test_it_rejects_a_new_batch_that_is_expired_on_its_receipt_date(): void
    {
        $service = new InventoryBatchService(
            $this->createMock(ProductBatchRepositoryInterface::class)
        );

        $this->expectException(\App\Core\Exceptions\BusinessException::class);
        $this->expectExceptionMessage(
            'A batch cannot be received after its expiry date.'
        );

        $service->createDraft([
            'product_id' => 1,
            'warehouse_id' => 1,
            'batch_no' => 'EXPIRED-RECEIPT-001',
            'unit_cost' => 10,
            'expiry_date' => '2026-11-13',
            'received_at' => '2026-11-14',
        ]);
    }


}