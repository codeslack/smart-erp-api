<?php

namespace App\Modules\SalesQuotation\Services;

use Illuminate\Support\Facades\DB;
use App\Modules\Product\Models\Product;
use App\Modules\Sales\Models\Sale;
use App\Modules\Sales\Models\SaleItem;
use App\Modules\Sales\Enums\SaleStatus;
use App\Modules\SalesQuotation\Models\SalesQuotation;
use App\Modules\SalesQuotation\Models\SalesQuotationItem;
use App\Modules\SalesQuotation\Enums\SalesQuotationStatus;
use App\Modules\SalesQuotation\Repositories\Contracts\SalesQuotationRepositoryInterface;

class SalesQuotationService
{
    public function __construct(
        protected SalesQuotationRepositoryInterface $repository
    ) {}

    public function getAll()
    {
        return $this->repository->paginate();
    }

    public function find(int $id)
    {
        return $this->repository->find($id);
    }

    public function create(array $data)
    {
        return DB::transaction(function () use ($data) {

            $items = $data['items'];

            unset($data['items']);

            $nextId = (
                SalesQuotation::max('id') ?? 0
            ) + 1;

            $data['quotation_no'] = sprintf(
                'QTN-%06d',
                $nextId
            );

            $data['status'] = SalesQuotationStatus::DRAFT;

            $quotation = $this->repository->create(
                $data
            );

            $subtotal = 0;

            foreach ($items as $item) {

                $product = Product::find(
                    $item['product_id']
                );

                abort_if(
                    !$product,
                    422,
                    'Invalid product.'
                );

                $lineTotal =
                    $item['quantity']
                    *
                    $item['unit_price'];

                SalesQuotationItem::create([
                    'sales_quotation_id' => $quotation->id,
                    'product_id'         => $item['product_id'],
                    'warehouse_id'       => $item['warehouse_id'],
                    'quantity'           => $item['quantity'],
                    'unit_price'         => $item['unit_price'],
                    'line_total'         => $lineTotal,
                ]);

                $subtotal += $lineTotal;
            }

            $quotation->update([
                'subtotal'    => $subtotal,
                'grand_total' => $subtotal,
            ]);

            return $quotation->load(
                'items'
            );
        });
    }

    public function update(
        int $id,
        array $data
    ) {
        $quotation = $this->find(
            $id
        );

        abort_if(
            $quotation->status !== SalesQuotationStatus::DRAFT,
            422,
            'Only draft quotations can be updated.'
        );

        return $this->repository->update(
            $id,
            $data
        );
    }

    public function approve(
        SalesQuotation $quotation
    ) {
        abort_if(
            $quotation->status !== SalesQuotationStatus::DRAFT,
            422,
            'Quotation already approved.'
        );

        $quotation->update([
            'status' => SalesQuotationStatus::APPROVED,
        ]);

        return $quotation->fresh();
    }

    public function convertToSale(
        SalesQuotation $quotation
    ) {
        return DB::transaction(function () use ($quotation) {

            abort_if(
                $quotation->status !== SalesQuotationStatus::APPROVED,
                422,
                'Quotation must be approved first.'
            );

            $quotation->loadMissing(
                'items'
            );

            $nextId = (
                Sale::max('id') ?? 0
            ) + 1;

            $sale = Sale::create([
                'tenant_id'   => tenant()->id,
                'customer_id' => $quotation->customer_id,
                'sale_no'     => sprintf(
                    'SAL-%06d',
                    $nextId
                ),
                'sale_date'   => now(),
                'subtotal'    => $quotation->subtotal,
                'grand_total' => $quotation->grand_total,
                'status'      => SaleStatus::DRAFT,
                'notes'       => sprintf(
                    'Generated from %s',
                    $quotation->quotation_no
                ),
            ]);

            foreach (
                $quotation->items as $item
            ) {

                SaleItem::create([
                    'sale_id'      => $sale->id,
                    'product_id'   => $item->product_id,
                    'warehouse_id' => $item->warehouse_id,
                    'quantity'     => $item->quantity,
                    'unit_price'   => $item->unit_price,
                    'line_total'   => $item->line_total,
                ]);
            }

            $quotation->update([
                'status' => SalesQuotationStatus::CONVERTED,
            ]);

            return $sale->load(
                'items'
            );
        });
    }

    public function delete(
        int $id
    ) {
        $quotation = $this->find(
            $id
        );

        abort_if(
            $quotation->status !== SalesQuotationStatus::DRAFT,
            422,
            'Only draft quotations can be deleted.'
        );

        return $this->repository->delete(
            $id
        );
    }
}
