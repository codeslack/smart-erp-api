<?php

namespace App\Modules\GoodsReceiptNote\Controllers;

use Illuminate\Http\JsonResponse;

use App\Http\Controllers\ApiController;

use App\Modules\GoodsReceiptNote\Services\GoodsReceiptNoteService;
use App\Modules\GoodsReceiptNote\Services\GoodsReceiptNoteDraftItemService;

use App\Modules\GoodsReceiptNote\Resources\GoodsReceiptNoteResource;
use App\Modules\GoodsReceiptNote\Resources\GoodsReceiptNoteItemResource;

use App\Modules\GoodsReceiptNote\Requests\StoreGoodsReceiptNoteRequest;
use App\Modules\GoodsReceiptNote\Requests\UpdateGoodsReceiptNoteRequest;
use App\Modules\GoodsReceiptNote\Requests\UpdateGoodsReceiptNoteItemRequest;

use App\Modules\GoodsReceiptNote\Repositories\Contracts\GoodsReceiptNoteItemRepositoryInterface;

class GoodsReceiptNoteController extends ApiController
{
    public function __construct(
        protected GoodsReceiptNoteService $goodsReceiptNoteService,
        protected GoodsReceiptNoteDraftItemService $draftItemService,
        protected GoodsReceiptNoteItemRepositoryInterface $itemRepository,
    ) {}

    public function store(
        StoreGoodsReceiptNoteRequest $request
    ): JsonResponse {
        $goodsReceiptNote = $this->goodsReceiptNoteService->create(
            $request->validated()
        );

        return $this->success(
            new GoodsReceiptNoteResource($goodsReceiptNote),
            'Goods receipt note created successfully.',
            201
        );
    }

    public function show(string $uuid): JsonResponse
    {
        $goodsReceiptNote = $this->goodsReceiptNoteService
            ->findByUuid($uuid);

        return $this->success(
            new GoodsReceiptNoteResource($goodsReceiptNote),
            'Goods receipt note retrieved successfully.'
        );
    }

    public function update(
        UpdateGoodsReceiptNoteRequest $request,
        string $uuid
    ): JsonResponse {
        $goodsReceiptNote = $this->goodsReceiptNoteService
            ->findByUuid($uuid);

        $goodsReceiptNote = $this->goodsReceiptNoteService->update(
            $goodsReceiptNote,
            $request->validated()
        );

        return $this->success(
            new GoodsReceiptNoteResource($goodsReceiptNote),
            'Goods receipt note updated successfully.'
        );
    }

    public function updateItem(
        UpdateGoodsReceiptNoteItemRequest $request,
        string $uuid,
        string $itemUuid
    ): JsonResponse {
        $goodsReceiptNote = $this->goodsReceiptNoteService
            ->findByUuid($uuid);

        $item = $this->itemRepository->findByUuid($itemUuid);

        if (
            ! $item
            || (int) $item->goods_receipt_note_id
                !== (int) $goodsReceiptNote->id
        ) {
            return $this->error(
                'Goods receipt note item not found.',
                null,
                404
            );
        }

        $item = $this->draftItemService->updateItem(
            $goodsReceiptNote,
            $item,
            $request->validated()
        );

        return $this->success(
            new GoodsReceiptNoteItemResource($item),
            'Goods receipt note item updated successfully.'
        );
    }

    public function deleteItem(
        string $uuid,
        string $itemUuid
    ): JsonResponse {
        $goodsReceiptNote = $this->goodsReceiptNoteService
            ->findByUuid($uuid);

        $item = $this->itemRepository->findByUuid($itemUuid);

        if (
            ! $item
            || (int) $item->goods_receipt_note_id
                !== (int) $goodsReceiptNote->id
        ) {
            return $this->error(
                'Goods receipt note item not found.',
                null,
                404
            );
        }

        $this->draftItemService->deleteItem(
            $goodsReceiptNote,
            $item
        );

        return $this->success(
            null,
            'Goods receipt note item deleted successfully.'
        );
    }

    public function confirm(string $uuid): JsonResponse
    {
        $goodsReceiptNote = $this->goodsReceiptNoteService
            ->findByUuid($uuid);

        $goodsReceiptNote = $this->goodsReceiptNoteService
            ->confirm($goodsReceiptNote);

        return $this->success(
            new GoodsReceiptNoteResource($goodsReceiptNote),
            'Goods receipt note confirmed successfully.'
        );
    }

    public function cancel(string $uuid): JsonResponse
    {
        $goodsReceiptNote = $this->goodsReceiptNoteService
            ->findByUuid($uuid);

        $goodsReceiptNote = $this->goodsReceiptNoteService
            ->cancel($goodsReceiptNote);

        return $this->success(
            new GoodsReceiptNoteResource($goodsReceiptNote),
            'Goods receipt note cancelled successfully.'
        );
    }
}