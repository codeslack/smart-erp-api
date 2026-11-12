<?php

namespace App\Modules\Purchase\Services\Actions;

use App\Core\Exceptions\BusinessException;
use App\Modules\Purchase\Models\Purchase;
use App\Modules\Purchase\Models\PurchaseItem;
use App\Modules\Purchase\Repositories\Contracts\PurchaseRepositoryInterface;
use App\Modules\Purchase\Repositories\Contracts\PurchaseItemRepositoryInterface;
use App\Modules\Purchase\Services\Calculators\PurchaseItemCalculator;
use App\Modules\Purchase\Services\Calculators\PurchaseCalculator;
use App\Modules\Purchase\Services\Support\PurchasePayloadResolver;
use App\Modules\Purchase\Services\Validators\PurchaseValidator;
use Illuminate\Support\Facades\DB;

class UpdatePurchaseAction
{
    public function __construct(
        protected PurchaseRepositoryInterface $repository,
        protected PurchaseItemRepositoryInterface $itemRepository,
        protected PurchasePayloadResolver $resolver,
        protected PurchaseValidator $validator,
        protected PurchaseItemCalculator $itemCalculator,
        protected PurchaseCalculator $calculator,
    ) {}

    public function execute(Purchase $purchase, array $data): Purchase
    {
        return DB::transaction(function () use ($purchase, $data) {
            $purchase = Purchase::query()
                ->lockForUpdate()
                ->findOrFail($purchase->id);

            if (! $purchase->isDraft()) {
                throw new BusinessException('Only draft purchases can be updated.');
            }

            $resolvedHeader = $this->resolver->resolveHeader($data);
            $rawItems = $resolvedHeader['items'] ?? null;
            $rawCharges = $resolvedHeader['charges'] ?? null;
            unset($resolvedHeader['items'], $resolvedHeader['charges']);

            $purchase->loadMissing('items', 'charges');

            $effectiveHeader = [...$purchase->toArray(), ...$resolvedHeader];

            if (! array_key_exists('discount_amount', $resolvedHeader)) {
                $existingItemDiscount = (float) $purchase->items->sum(
                    'discount_amount'
                );

                // Purchase.discount_amount stores item + header discounts.
                // Recover only the header portion before recalculating.
                $effectiveHeader['discount_amount'] = max(
                    0,
                    (float) $purchase->discount_amount - $existingItemDiscount
                );
            }

            $recalculationFields = [
                'tax_calculation_mode',
                'discount_type',
                'discount_rate',
                'discount_amount',
                'rounding_amount',
            ];

            $shouldRecalculate = $rawItems !== null
                || $rawCharges !== null
                || count(array_intersect(
                    $recalculationFields,
                    array_keys($resolvedHeader)
                )) > 0;

            if ($shouldRecalculate) {
                $effectiveWarehouseId = $resolvedHeader['warehouse_id']
                    ?? $purchase->warehouse_id;

                if ($rawItems !== null) {
                    $resolvedItems = $this->resolver->resolveItems(
                        $rawItems,
                        $effectiveWarehouseId
                    );

                    $this->validator->validateDraft(
                        $effectiveHeader,
                        $resolvedItems
                    );

                    $calculatedItems = [];

                    foreach ($resolvedItems as $item) {
                        $calc = $this->itemCalculator->calculate(
                            $item,
                            (string) ($effectiveHeader['tax_calculation_mode'] ?? 'exclusive')
                        );

                        if (isset($item['batches'])) {
                            $calc['batches'] = $item['batches'];
                        }

                        if (isset($item['serials'])) {
                            $calc['serials'] = $item['serials'];
                        }

                        $calculatedItems[] = $calc;
                    }
                } else {
                    $purchase->loadMissing('items');

                    $calculatedItems = $purchase->items
                        ->map(function (PurchaseItem $item) use ($effectiveHeader) {
                            $taxRate = (float) $item->tax_rate;

                            return [
                                ...$this->itemCalculator->calculate([
                                    'product_id' => $item->product_id,
                                    'product_variant_id' => $item->product_variant_id,
                                    'warehouse_id' => $item->warehouse_id,
                                    'unit_id' => $item->unit_id,
                                    'quantity' => $item->quantity,
                                    'unit_cost' => $item->unit_cost,
                                    'base_quantity' => $item->base_quantity,
                                    'base_unit_cost' => $item->base_unit_cost,
                                    'discount_type' => $item->discount_type,
                                    'discount_rate' => $item->discount_rate,
                                    'discount_amount' => $item->discount_amount,
                                    'tax_rate' => $taxRate,
                                    'tax_amount' => $taxRate > 0
                                        ? 0
                                        : $item->tax_amount,
                                ], (string) (
                                    $effectiveHeader['tax_calculation_mode']
                                    ?? 'exclusive'
                                )),
                                'id' => $item->id,
                            ];
                        })
                        ->all();
                }

                $calculatedItems = $this->calculator->applyHeaderDiscount(
                    $calculatedItems,
                    $effectiveHeader
                );

                foreach ($calculatedItems as &$itemData) {
                    $baseQuantity = (float) ($itemData['base_quantity'] ?? 0);

                    if ($baseQuantity > 0) {
                        $itemData['base_unit_cost'] = round(
                            (float) ($itemData['subtotal'] ?? 0) / $baseQuantity,
                            8
                        );
                    }
                }
                unset($itemData);

                if ($rawItems !== null) {
                    $this->saveItems(
                        $purchase,
                        $calculatedItems,
                        $effectiveWarehouseId
                    );
                } else {
                    foreach ($calculatedItems as $itemData) {
                        $existingItem = $purchase->items->firstWhere(
                            'id',
                            $itemData['id']
                        );

                        if ($existingItem) {
                            $existingItem->update([
                                'line_total' => $itemData['line_total'],
                                'subtotal' => $itemData['subtotal'],
                                'discount_type' => $itemData['discount_type'],
                                'discount_rate' => $itemData['discount_rate'],
                                'discount_percentage' => $itemData['discount_percentage'],
                                'discount_amount' => $itemData['discount_amount'],
                                'tax_rate' => $itemData['tax_rate'],
                                'tax_amount' => $itemData['tax_amount'],
                                'total_amount' => $itemData['total_amount'],
                                'base_unit_cost' => $itemData['base_unit_cost'],
                            ]);
                        }
                    }
                }

                $itemsSubtotal = (float) array_sum(
                    array_column($calculatedItems, 'line_total')
                );

                if ($rawCharges !== null) {
                    $resolvedCharges = $this->resolver->resolveCharges(
                        $rawCharges,
                        $itemsSubtotal
                    );

                    $this->saveCharges($purchase, $resolvedCharges);
                } else {
                    $purchase->loadMissing('charges');
                    $resolvedCharges = $purchase->charges->toArray();
                }

                $totals = $this->calculator->calculateTotals(
                    $calculatedItems,
                    $effectiveHeader,
                    $resolvedCharges
                );

                $resolvedHeader['subtotal'] = $totals['subtotal'];
                $resolvedHeader['discount_type'] = $totals['discount_type'];
                $resolvedHeader['discount_rate'] = $totals['discount_rate'];
                $resolvedHeader['discount_amount'] = $totals['discount_amount'];
                $resolvedHeader['charges_subtotal'] = $totals['charges_subtotal'];
                $resolvedHeader['charges_tax_amount'] = $totals['charges_tax_amount'];
                $resolvedHeader['tax_amount'] = $totals['tax_amount'];
                $resolvedHeader['rounding_amount'] = $totals['rounding_amount'];
                $resolvedHeader['grand_total'] = $totals['grand_total'];
                $resolvedHeader['due_amount'] = $totals['due_amount'];
            }

            $resolvedHeader['updated_by'] = userId();
            $this->repository->update($purchase, $resolvedHeader);

            return $purchase->fresh()->load([
                'supplier',
                'warehouse',
                'purchaseOrder',
                'goodsReceiptNote',
                'paymentTerm',
                'charges.chartOfAccount',
                'charges.tax',
                'items.product',
                'items.variant',
                'items.warehouse',
                'items.unit',
                'items.tax',
                'items.batches',
                'items.serials',
            ]);
        });
    }

    protected function saveItems(Purchase $purchase, array $calculatedItems, int $warehouseId): void
    {
        $this->itemRepository->deleteByPurchaseId($purchase->id);

        foreach ($calculatedItems as $itemData) {
            $batches = $itemData['batches'] ?? [];
            $serials = $itemData['serials'] ?? [];
            unset($itemData['batches'], $itemData['serials']);

            $item = PurchaseItem::create([
                ...$itemData,
                'purchase_id' => $purchase->id,
                'warehouse_id' => $itemData['warehouse_id'] ?? $warehouseId,
            ]);

            foreach ($batches as $batch) {
                $item->batches()->create([
                    'tenant_id' => $purchase->tenant_id,
                    'purchase_id' => $purchase->id,
                    'batch_no' => $batch['batch_no'],
                    'quantity' => $batch['quantity'],
                    'base_quantity' => $batch['base_quantity'] ?? null,
                    'manufacturing_date' => $batch['manufacturing_date'] ?? null,
                    'expiry_date' => $batch['expiry_date'] ?? null,
                ]);
            }

            foreach ($serials as $serial) {
                $serialNo = is_array($serial) ? ($serial['serial_number'] ?? $serial['serial_no'] ?? '') : (string) $serial;
                $item->serials()->create([
                    'tenant_id' => $purchase->tenant_id,
                    'purchase_id' => $purchase->id,
                    'serial_number' => $serialNo,
                ]);
            }
        }
    }

    protected function saveCharges(Purchase $purchase, array $charges): void
    {
        $purchase->charges()->delete();

        foreach ($charges as $c) {
            $purchase->charges()->create([
                'tenant_id' => $purchase->tenant_id,
                'charge_type' => $c['charge_type'] ?? 'other',
                'name' => $c['name'] ?? 'Charge',
                'calculation_method' => $c['calculation_method'] ?? 'fixed',
                'calculation_value' => $c['calculation_value'] ?? 0,
                'amount' => $c['amount'] ?? 0,
                'tax_id' => $c['tax_id'] ?? null,
                'tax_rate' => $c['tax_rate'] ?? 0,
                'tax_amount' => $c['tax_amount'] ?? 0,
                'treatment' => $c['treatment'] ?? 'expense',
                'allocation_method' => $c['allocation_method'] ?? 'by_value',
                'affects_inventory_cost' => (bool) ($c['affects_inventory_cost'] ?? false),
                'chart_of_account_id' => $c['chart_of_account_id'] ?? null,
                'sort_order' => (int) ($c['sort_order'] ?? 0),
            ]);
        }
    }
}
