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
        if (! $purchase->isDraft()) {
            throw new BusinessException('Only draft purchases can be updated.');
        }

        return DB::transaction(function () use ($purchase, $data) {
            $resolvedHeader = $this->resolver->resolveHeader($data);
            $rawItems = $resolvedHeader['items'] ?? null;
            $rawCharges = $resolvedHeader['charges'] ?? null;
            unset($resolvedHeader['items'], $resolvedHeader['charges']);

            $effectiveHeader = [...$purchase->toArray(), ...$resolvedHeader];

            if ($rawItems !== null || $rawCharges !== null) {
                $effectiveWarehouseId = $resolvedHeader['warehouse_id'] ?? $purchase->warehouse_id;

                $calculatedItems = [];
                if ($rawItems !== null) {
                    $resolvedItems = $this->resolver->resolveItems($rawItems, $effectiveWarehouseId);
                    $this->validator->validateDraft($effectiveHeader, $resolvedItems);

                    foreach ($resolvedItems as $item) {
                        $calc = $this->itemCalculator->calculate($item);
                        if (isset($item['batches'])) $calc['batches'] = $item['batches'];
                        if (isset($item['serials'])) $calc['serials'] = $item['serials'];
                        $calculatedItems[] = $calc;
                    }

                    $this->saveItems($purchase, $calculatedItems, $effectiveWarehouseId);
                } else {
                    $calculatedItems = $purchase->items->toArray();
                }

                $itemsSubtotal = (float) array_sum(array_column($calculatedItems, 'line_total'));

                $resolvedCharges = [];
                if ($rawCharges !== null) {
                    $resolvedCharges = $this->resolver->resolveCharges($rawCharges, $itemsSubtotal);
                    $this->saveCharges($purchase, $resolvedCharges);
                } else {
                    $resolvedCharges = $purchase->charges->toArray();
                }

                $totals = $this->calculator->calculateTotals($calculatedItems, $effectiveHeader, $resolvedCharges);

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
