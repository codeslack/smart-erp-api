<?php

namespace Tests\Unit\Modules\GoodsReceiptNote;

use Tests\TestCase;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

use App\Modules\Product\Models\Product;
use App\Modules\Product\Enums\ProductTypeEnum;
use App\Modules\Product\Enums\InventoryTrackingTypeEnum;
use App\Modules\GoodsReceiptNote\Models\GoodsReceiptNote;
use App\Modules\GoodsReceiptNote\Models\GoodsReceiptNoteItem;
use App\Modules\GoodsReceiptNote\Models\GoodsReceiptNoteItemBatch;
use App\Modules\GoodsReceiptNote\Services\Inventory\GoodsReceiptNoteInventoryValidator;

class GoodsReceiptNoteInventoryValidatorTest extends TestCase
{
    public function test_expiry_tracked_grn_batch_requires_an_expiry_date(): void
    {
        $item = $this->makeItem(
            expiryDate: null,
            grnDate: '2026-11-14',
        );

        try {
            app(GoodsReceiptNoteInventoryValidator::class)->validate($item);

            $this->fail('Expiry-tracked GRN batches must have an expiry date.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('batches', $exception->errors());
        }
    }

    public function test_grn_cannot_receive_a_batch_that_is_already_expired(): void
    {
        $item = $this->makeItem(
            expiryDate: '2026-11-13',
            grnDate: '2026-11-14',
        );

        try {
            app(GoodsReceiptNoteInventoryValidator::class)->validate($item);

            $this->fail('A GRN must not receive a batch already expired on the receipt date.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('batches', $exception->errors());
        }
    }

    protected function makeItem(
        ?string $expiryDate,
        string $grnDate,
    ): GoodsReceiptNoteItem {
        $product = new Product();
        $product->forceFill([
            'product_type' => ProductTypeEnum::PRODUCT,
            'inventory_tracking_type' => InventoryTrackingTypeEnum::BATCH,
            'has_expiry' => true,
        ]);

        $grn = new GoodsReceiptNote();
        $grn->forceFill([
            'grn_date' => $grnDate,
        ]);

        $batch = new GoodsReceiptNoteItemBatch();
        $batch->forceFill([
            'batch_no' => 'GRN-EXPIRY-001',
            'quantity' => 5,
            'expiry_date' => $expiryDate,
        ]);

        $item = new GoodsReceiptNoteItem();
        $item->forceFill([
            'base_quantity' => 5,
            'base_unit_cost' => 10,
        ]);

        $item->setRelation('product', $product);
        $item->setRelation('goodsReceiptNote', $grn);
        $item->setRelation('batches', new Collection([$batch]));

        return $item;
    }
}
