<?php

namespace App\Modules\SalesQuotation\Repositories;

use App\Modules\SalesQuotation\Models\SalesQuotation;
use App\Modules\SalesQuotation\Repositories\Contracts\SalesQuotationRepositoryInterface;

class SalesQuotationRepository
    implements SalesQuotationRepositoryInterface
{
    public function paginate(
        int $perPage = 15
    ) {
        return SalesQuotation::with(
            'items'
        )->latest()->paginate(
            $perPage
        );
    }

    public function find(
        int $id
    ) {
        return SalesQuotation::with(
            'items'
        )->findOrFail(
            $id
        );
    }

    public function create(
        array $data
    ) {
        return SalesQuotation::create(
            $data
        );
    }

    public function update(
        int $id,
        array $data
    ) {
        $quotation = $this->find(
            $id
        );

        $quotation->update(
            $data
        );

        return $quotation->fresh(
            'items'
        );
    }

    public function delete(
        int $id
    ) {
        return SalesQuotation::destroy(
            $id
        );
    }
}
