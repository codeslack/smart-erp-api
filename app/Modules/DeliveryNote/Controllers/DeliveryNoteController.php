<?php

namespace App\Modules\DeliveryNote\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\DeliveryNote\Models\DeliveryNote;
use App\Modules\DeliveryNote\Services\DeliveryNoteService;
use App\Modules\DeliveryNote\Resources\DeliveryNoteResource;
use App\Modules\DeliveryNote\Requests\StoreDeliveryNoteRequest;
use App\Modules\DeliveryNote\Requests\UpdateDeliveryNoteRequest;

class DeliveryNoteController extends Controller
{
    public function __construct(
        protected DeliveryNoteService $service
    ) {}

    public function index()
    {
        return DeliveryNoteResource::collection(
            $this->service->getAll()
        );
    }

    public function store(
        StoreDeliveryNoteRequest $request
    ) {
        return new DeliveryNoteResource(
            $this->service->create(
                $request->validated()
            )
        );
    }

    public function show(
        DeliveryNote $deliveryNote
    ) {
        return new DeliveryNoteResource(
            $this->service->find(
                $deliveryNote->id
            )
        );
    }

    public function update(
        UpdateDeliveryNoteRequest $request,
        DeliveryNote $deliveryNote
    ) {
        return new DeliveryNoteResource(
            $this->service->update(
                $deliveryNote->id,
                $request->validated()
            )
        );
    }

    public function deliver(
        DeliveryNote $deliveryNote
    ) {
        return response()->json([
            'success' => true,
            'message' => 'Delivery Note delivered successfully',
            'data' => new DeliveryNoteResource(
                $this->service->deliver(
                    $deliveryNote
                )
            ),
        ]);
    }

    public function convertToSale(
        DeliveryNote $deliveryNote
    ) {
        return response()->json([
            'success' => true,
            'message' => 'Delivery Note converted successfully',
            'data' => $this->service->convertToSale(
                $deliveryNote
            ),
        ]);
    }

    public function cancel(
        DeliveryNote $deliveryNote
    ) {
        return response()->json([
            'success' => true,
            'message' => 'Delivery Note cancelled successfully',
            'data' => new DeliveryNoteResource(
                $this->service->cancel(
                    $deliveryNote
                )
            ),
        ]);
    }

    public function destroy(
        DeliveryNote $deliveryNote
    ) {
        $this->service->delete(
            $deliveryNote->id
        );

        return response()->json([
            'message' => 'Delivery Note deleted successfully',
        ]);
    }
}
