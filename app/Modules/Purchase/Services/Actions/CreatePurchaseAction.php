<?php

namespace App\Modules\Purchase\Services\Actions;

use App\Modules\Purchase\Models\Purchase;
use App\Modules\Purchase\Models\PurchaseItem;
use App\Modules\Purchase\Enums\PurchaseStatusEnum;
use App\Modules\Purchase\Repositories\Contracts\PurchaseRepositoryInterface;
use App\Modules\Purchase\Services\Calculators\PurchaseItemCalculator;
use App\Modules\Purchase\Services\Calculators\PurchaseCalculator;
use App\Modules\Purchase\Services\Support\PurchasePayloadResolver;
use App\Modules\Purchase\Services\Validators\PurchaseValidator;
use App\Modules\DocumentNumber\Enums\DocumentType;
use Illuminate\Support\Facades\DB;

class CreatePurchaseAction
{
    public function __construct(
        protected PurchaseRepositoryInterface $repository,
        protected PurchasePayloadResolver $resolver,
        protected PurchaseValidator $validator,
        protected PurchaseItemCalculator $itemCalculator,
        protected PurchaseCalculator $calculator,
    ) {}

    public function execute(array $data): Purchase
    {
        return DB::transaction(function () use ($data) {
            $resolvedHeader = $this->resolver->resolveHeader($data);
            $rawItems = $resolvedHeader['items'] ?? [];
            $rawCharges = $resolvedHeader['charges'] ?? [];
            unset($resolvedHeader['items'], $resolvedHeader['charges']);

            $resolvedItems = $this->resolver->resolveItems(
                $rawItems,
                $resolvedHeader['warehouse_id'] ?? null
            );

            // Validate business rules, product variants, and document links
            $this->validator->validateDraft($resolvedHeader, $resolvedItems);

            $calculatedItems = [];
            foreach ($resolvedItems as $item) {
                $calculated = $this->itemCalculator->calculate(
                    $item,
                    (string) ($resolvedHeader['tax_calculation_mode'] ?? 'exclusive')
                );
                if (isset($item['batches'])) {
                    $calculated['batches'] = $item['batches'];
                }
                if (isset($item['serials'])) {
                    $calculated['serials'] = $item['serials'];
                }
                $calculatedItems[] = $calculated;
            }

            $calculatedItems = $this->calculator->applyHeaderDiscount(
                $calculatedItems,
                $resolvedHeader
            );

            foreach ($calculatedItems as &$itemData) {
                $baseQuantity = (float) ($itemData['base_quantity'] ?? 0);

                if ($baseQuantity > 0) {
                    // Persist tax-exclusive, discount-adjusted cost per base unit.
                    $itemData['base_unit_cost'] = round(
                        (float) ($itemData['subtotal'] ?? 0) / $baseQuantity,
                        8
                    );
                }
            }
            unset($itemData);

            $itemsSubtotal = (float) array_sum(array_column($calculatedItems, 'line_total'));
            $resolvedCharges = $this->resolver->resolveCharges($rawCharges, $itemsSubtotal);

            $totals = $this->calculator->calculateTotals(
                $calculatedItems,
                $resolvedHeader,
                $resolvedCharges
            );

            $purchaseNo = $resolvedHeader['purchase_no'] ?? nextDocumentNumber(DocumentType::PURCHASE_INVOICE);

            /** @var Purchase $purchase */
            $purchase = $this->repository->create([
                ...$resolvedHeader,
                'purchase_no' => $purchaseNo,
                'status' => PurchaseStatusEnum::DRAFT->value,
                'subtotal' => $totals['subtotal'],
                'discount_type' => $totals['discount_type'],
                'discount_rate' => $totals['discount_rate'],
                'discount_amount' => $totals['discount_amount'],
                'charges_subtotal' => $totals['charges_subtotal'],
                'charges_tax_amount' => $totals['charges_tax_amount'],
                'tax_amount' => $totals['tax_amount'],
                'rounding_amount' => $totals['rounding_amount'],
                'grand_total' => $totals['grand_total'],
                'paid_amount' => 0,
                'due_amount' => $totals['due_amount'],
                'created_by' => userId(),
            ]);

            foreach ($calculatedItems as $itemData) {
                $batches = $itemData['batches'] ?? [];
                $serials = $itemData['serials'] ?? [];
                unset($itemData['batches'], $itemData['serials']);

                $purchaseItem = PurchaseItem::create([
                    ...$itemData,
                    'purchase_id' => $purchase->id,
                    'warehouse_id' => $itemData['warehouse_id'] ?? $purchase->warehouse_id,
                ]);

                foreach ($batches as $batch) {
                    $purchaseItem->batches()->create([
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
                    $purchaseItem->serials()->create([
                        'tenant_id' => $purchase->tenant_id,
                        'purchase_id' => $purchase->id,
                        'serial_number' => $serialNo,
                    ]);
                }
            }

            foreach ($resolvedCharges as $charge) {
                $purchase->charges()->create([
                    'tenant_id' => $purchase->tenant_id,
                    'charge_type' => $charge['charge_type'] ?? 'other',
                    'name' => $charge['name'] ?? 'Charge',
                    'calculation_method' => $charge['calculation_method'] ?? 'fixed',
                    'calculation_value' => $charge['calculation_value'] ?? 0,
                    'amount' => $charge['amount'] ?? 0,
                    'tax_id' => $charge['tax_id'] ?? null,
                    'tax_rate' => $charge['tax_rate'] ?? 0,
                    'tax_amount' => $charge['tax_amount'] ?? 0,
                    'treatment' => $charge['treatment'] ?? 'expense',
                    'allocation_method' => $charge['allocation_method'] ?? 'by_value',
                    'affects_inventory_cost' => (bool) ($charge['affects_inventory_cost'] ?? false),
                    'chart_of_account_id' => $charge['chart_of_account_id'] ?? null,
                    'sort_order' => (int) ($charge['sort_order'] ?? 0),
                ]);
            }

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
}
