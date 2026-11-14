<?php

namespace Tests\Unit\Modules\GoodsReceiptNote;

use Tests\TestCase;

use Illuminate\Database\QueryException;
use Illuminate\Validation\ValidationException;

use App\Modules\GoodsReceiptNote\Models\GoodsReceiptNote;
use App\Modules\GoodsReceiptNote\Models\GoodsReceiptNoteItem;
use App\Modules\GoodsReceiptNote\Models\GoodsReceiptNoteItemBatch;
use App\Modules\GoodsReceiptNote\Services\Inventory\GoodsReceiptNoteInventoryBatchService;

use App\Modules\Inventory\Enums\ProductBatchStatusEnum;
use App\Modules\Inventory\Models\ProductBatch;
use App\Modules\Inventory\Services\InventoryBatchService;

class GoodsReceiptNoteInventoryBatchServiceTest extends TestCase
{
    public function test_it_recovers_from_a_duplicate_batch_insert_and_reuses_the_active_batch(): void
    {
        $batchService = $this->createMock(InventoryBatchService::class);

        $batch = new ProductBatch();
        $batch->forceFill([
            $batch->getKeyName() => 55,
            'status' => ProductBatchStatusEnum::ACTIVE,
        ]);

        $batchService
            ->expects($this->once())
            ->method('findByBatchNumber')
            ->with('BATCH-001', 10, 20, null)
            ->willReturn(null);

        $batchService
            ->expects($this->once())
            ->method('createDraft')
            ->willThrowException($this->duplicateBatchException());

        $batchService
            ->expects($this->once())
            ->method('isDuplicateBatchException')
            ->willReturn(true);

        $batchService
            ->expects($this->once())
            ->method('findByBatchNumberForUpdate')
            ->with('BATCH-001', 10, 20, null)
            ->willReturn($batch);

        $batchService
            ->expects($this->once())
            ->method('lock')
            ->with(55)
            ->willReturn($batch);

        $batchService
            ->expects($this->never())
            ->method('activate');

        $service = new GoodsReceiptNoteInventoryBatchService(
            $batchService
        );

        $result = $service->resolve(
            $this->makeGrn(),
            $this->makeItem(),
            $this->makeGrnBatch(),
            100,
        );

        $this->assertSame($batch, $result);
    }


    public function test_it_rejects_an_existing_batch_with_a_different_expiry_date(): void
    {
        $batchService = $this->createMock(InventoryBatchService::class);

        $batch = new ProductBatch();
        $batch->forceFill([
            $batch->getKeyName() => 55,
            'status' => ProductBatchStatusEnum::ACTIVE,
            'expiry_date' => '2028-06-30',
        ]);

        $batchService
            ->expects($this->once())
            ->method('findByBatchNumber')
            ->with('BATCH-001', 10, 20, null)
            ->willReturn($batch);

        $batchService
            ->expects($this->once())
            ->method('lock')
            ->with(55)
            ->willReturn($batch);

        $grnBatch = $this->makeGrnBatch();
        $grnBatch->expiry_date = '2028-12-31';

        $service = new GoodsReceiptNoteInventoryBatchService(
            $batchService
        );

        try {
            $service->resolve(
                $this->makeGrn(),
                $this->makeItem(),
                $grnBatch,
                100,
            );

            $this->fail('Expected an existing batch expiry mismatch to be rejected.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey(
                'batches',
                $exception->errors()
            );
        }
    }

    public function test_it_rethrows_an_unrelated_database_exception(): void
    {
        $batchService = $this->createMock(InventoryBatchService::class);

        $exception = new QueryException(
            'mysql',
            'insert into product_batches (...) values (...)',
            [],
            new \PDOException(
                'SQLSTATE[23000]: Integrity constraint violation: '
                    . '1062 Duplicate entry for unrelated_unique_index',
                1062
            )
        );

        $batchService
            ->expects($this->once())
            ->method('findByBatchNumber')
            ->willReturn(null);

        $batchService
            ->expects($this->once())
            ->method('createDraft')
            ->willThrowException($exception);

        $batchService
            ->expects($this->once())
            ->method('isDuplicateBatchException')
            ->with($exception)
            ->willReturn(false);

        $batchService
            ->expects($this->never())
            ->method('findByBatchNumberForUpdate');

        $service = new GoodsReceiptNoteInventoryBatchService(
            $batchService
        );

        try {
            $service->resolve(
                $this->makeGrn(),
                $this->makeItem(),
                $this->makeGrnBatch(),
                100,
            );

            $this->fail('Expected the database exception to be rethrown.');
        } catch (QueryException $thrown) {
            $this->assertSame($exception, $thrown);
        }
    }

    public function test_it_rejects_an_inactive_batch_found_during_recovery(): void
    {
        $batchService = $this->createMock(InventoryBatchService::class);

        $batch = new ProductBatch();
        $batch->forceFill([
            $batch->getKeyName() => 55,
            'status' => ProductBatchStatusEnum::DRAFT,
        ]);

        $batchService
            ->method('findByBatchNumber')
            ->willReturn(null);

        $batchService
            ->method('createDraft')
            ->willThrowException($this->duplicateBatchException());

        $batchService
            ->method('isDuplicateBatchException')
            ->willReturn(true);

        $batchService
            ->method('findByBatchNumberForUpdate')
            ->willReturn($batch);

        $batchService
            ->method('lock')
            ->with(55)
            ->willReturn($batch);

        $service = new GoodsReceiptNoteInventoryBatchService(
            $batchService
        );

        try {
            $service->resolve(
                $this->makeGrn(),
                $this->makeItem(),
                $this->makeGrnBatch(),
                100,
            );

            $this->fail('Expected an inactive batch to be rejected.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey(
                'batches',
                $exception->errors()
            );
        }
    }

    private function duplicateBatchException(): QueryException
    {
        return new QueryException(
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
    }

    private function makeGrn(): GoodsReceiptNote
    {
        $grn = new GoodsReceiptNote();

        $grn->forceFill([
            $grn->getKeyName() => 100,
            'grn_number' => 'GRN-TEST-001',
            'grn_date' => '2026-10-22',
        ]);

        return $grn;
    }

    private function makeItem(): GoodsReceiptNoteItem
    {
        $item = new GoodsReceiptNoteItem();

        $item->forceFill([
            'product_id' => 10,
            'warehouse_id' => 20,
            'product_variant_id' => null,
            'base_unit_cost' => 100,
        ]);

        return $item;
    }

    private function makeGrnBatch(): GoodsReceiptNoteItemBatch
    {
        $batch = new GoodsReceiptNoteItemBatch();

        $batch->forceFill([
            'batch_no' => 'BATCH-001',
            'expiry_date' => null,
        ]);

        return $batch;
    }
}
