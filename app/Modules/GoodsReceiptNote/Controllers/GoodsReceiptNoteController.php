<?php

namespace App\Modules\GoodsReceiptNote\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\GoodsReceiptNote\Models\GoodsReceiptNote;
use App\Modules\GoodsReceiptNote\Services\GoodsReceiptNoteService;
use App\Modules\GoodsReceiptNote\Resources\GoodsReceiptNoteResource;
use App\Modules\GoodsReceiptNote\Requests\StoreGoodsReceiptNoteRequest;
use App\Modules\GoodsReceiptNote\Requests\UpdateGoodsReceiptNoteRequest;

class GoodsReceiptNoteController extends Controller
{
    public function __construct(
        protected GoodsReceiptNoteService $service
    ) {}

    public function index()
    {
        return GoodsReceiptNoteResource::collection(
            $this->service->getAll()
        );
    }

    public function store(
        StoreGoodsReceiptNoteRequest $request
    ) {
        return new GoodsReceiptNoteResource(
            $this->service->create(
                $request->validated()
            )
        );
    }

    public function show(
        GoodsReceiptNote $goodsReceiptNote
    ) {
        return new GoodsReceiptNoteResource(
            $this->service->find(
                $goodsReceiptNote->id
            )
        );
    }

    public function update(
        UpdateGoodsReceiptNoteRequest $request,
        GoodsReceiptNote $goodsReceiptNote
    ) {
        return new GoodsReceiptNoteResource(
            $this->service->update(
                $goodsReceiptNote->id,
                $request->validated()
            )
        );
    }

    public function receive(
        GoodsReceiptNote $goodsReceiptNote
    ) {
        return response()->json([
            'success' => true,
            'message' => 'Goods Receipt Note received successfully',
            'data' => new GoodsReceiptNoteResource(
                $this->service->receive(
                    $goodsReceiptNote
                )
            ),
        ]);
    }

    public function convertToPurchase(
        GoodsReceiptNote $goodsReceiptNote
    ) {
        return response()->json([
            'success' => true,
            'message' => 'GRN converted to Purchase successfully',
            'data' => $this->service->convertToPurchase(
                $goodsReceiptNote
            ),
        ]);
    }

    public function cancel(
        GoodsReceiptNote $goodsReceiptNote
    ) {
        return response()->json([
            'success' => true,
            'message' => 'Goods Receipt Note cancelled successfully',
            'data' => new GoodsReceiptNoteResource(
                $this->service->cancel(
                    $goodsReceiptNote
                )
            ),
        ]);
    }

    public function destroy(
        GoodsReceiptNote $goodsReceiptNote
    ) {
        $this->service->delete(
            $goodsReceiptNote->id
        );

        return response()->json([
            'message' => 'Goods Receipt Note deleted successfully',
        ]);
    }
}
