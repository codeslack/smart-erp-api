<?php

namespace App\Modules\DeliveryNote\Repositories;

use App\Modules\DeliveryNote\Models\DeliveryNote;
use App\Modules\DeliveryNote\Repositories\Contracts\DeliveryNoteRepositoryInterface;

class DeliveryNoteRepository
    implements DeliveryNoteRepositoryInterface
{
    public function paginate(
        int $perPage = 15
    ) {
        return DeliveryNote::with([
            'items',
        ])->latest()->paginate(
            $perPage
        );
    }

    public function find(
        int $id
    ) {
        return DeliveryNote::with([
            'items',
        ])->findOrFail(
            $id
        );
    }

    public function create(
        array $data
    ) {
        return DeliveryNote::create(
            $data
        );
    }

    public function update(
        int $id,
        array $data
    ) {
        $deliveryNote = $this->find(
            $id
        );

        $deliveryNote->update(
            $data
        );

        return $deliveryNote->fresh();
    }

    public function delete(
        int $id
    ) {
        return DeliveryNote::destroy(
            $id
        );
    }
}
