<?php

namespace App\Modules\GoodsReceiptNote\Repositories;

use App\Modules\GoodsReceiptNote\Models\GoodsReceiptNote;
use App\Modules\GoodsReceiptNote\Repositories\Contracts\GoodsReceiptNoteRepositoryInterface;

class GoodsReceiptNoteRepository
    implements GoodsReceiptNoteRepositoryInterface
{
    public function paginate(
        int $perPage = 15
    ) {
        return GoodsReceiptNote::with(
            'items'
        )->latest()->paginate(
            $perPage
        );
    }

    public function find(
        int $id
    ) {
        return GoodsReceiptNote::with(
            'items'
        )->findOrFail(
            $id
        );
    }

    public function create(
        array $data
    ) {
        return GoodsReceiptNote::create(
            $data
        );
    }

    public function update(
        int $id,
        array $data
    ) {
        $grn = $this->find(
            $id
        );

        $grn->update(
            $data
        );

        return $grn->fresh(
            'items'
        );
    }

    public function delete(
        int $id
    ) {
        return GoodsReceiptNote::destroy(
            $id
        );
    }
}
