<?php

namespace App\Modules\PurchaseOrder\Services;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

use App\Core\Exceptions\BusinessException;
use App\Core\Exceptions\NotFoundException;
use App\Core\Services\BaseService;

use App\Modules\DocumentNumber\Enums\DocumentType;

use App\Modules\PurchaseOrder\Enums\PurchaseOrderStatusEnum;
use App\Modules\PurchaseOrder\Models\PurchaseOrder;
use App\Modules\PurchaseOrder\Repositories\Contracts\PurchaseOrderItemRepositoryInterface;
use App\Modules\PurchaseOrder\Repositories\Contracts\PurchaseOrderRepositoryInterface;
use App\Modules\PurchaseOrder\Services\Calculators\PurchaseOrderCalculator;
use App\Modules\PurchaseOrder\Services\Calculators\PurchaseOrderItemCalculator;

use App\Modules\Product\Repositories\Contracts\ProductVariantRepositoryInterface;
use App\Modules\Supplier\Repositories\Contracts\SupplierRepositoryInterface;
use App\Modules\Warehouse\Repositories\Contracts\WarehouseRepositoryInterface;

class PurchaseOrderService extends BaseService
{
    public function __construct(
        private readonly PurchaseOrderRepositoryInterface $repository,
        private readonly PurchaseOrderItemRepositoryInterface $itemRepository,
        private readonly SupplierRepositoryInterface $supplierRepository,
        private readonly WarehouseRepositoryInterface $warehouseRepository,
        private readonly ProductVariantRepositoryInterface $productVariants,
        private readonly PurchaseOrderCalculator $orderCalculator,
        private readonly PurchaseOrderItemCalculator $itemCalculator,
    ) {
    }

    public function paginate(
        array $filters = [],
        int $perPage = 15
    ): LengthAwarePaginator {
        return $this->repository->paginateWithFilters(
            $filters,
            $perPage
        );
    }

    public function findByUuid(
        string $uuid
    ): ?PurchaseOrder {
        return $this->repository->getWithRelations($uuid);
    }

    public function findByOrderNumber(
        string $orderNumber
    ): ?PurchaseOrder {
        return $this->repository->findByOrderNumber(
            $orderNumber
        );
    }

    public function getItems(
        string $uuid
    ): Collection {
        $purchaseOrder = $this->getRequired($uuid);

        return $this->itemRepository->findByPurchaseOrder(
            $purchaseOrder->id
        );
    }

    public function create(
        array $data
    ): PurchaseOrder {
        return DB::transaction(
            function () use ($data): PurchaseOrder {
                $this->validateReferences($data);

                $items = $data['items'] ?? [];

                unset($data['items']);

                $data['order_number'] = nextDocumentNumber(
                    DocumentType::PURCHASE_ORDER
                );

                $data['status'] =
                    PurchaseOrderStatusEnum::DRAFT->value;

                $calculatedItems = $this->calculateItems(
                    $items
                );

                $totals = $this->calculateOrderTotals(
                    $calculatedItems,
                    $data
                );

                $data = array_merge(
                    $data,
                    $totals
                );

                $purchaseOrder = $this->repository->create(
                    $data
                );

                $this->itemRepository->createMany(
                    $purchaseOrder->id,
                    $calculatedItems
                );

                return $this->repository->getWithRelations(
                    $purchaseOrder->uuid
                );
            }
        );
    }

    public function update(
        string $uuid,
        array $data
    ): PurchaseOrder {
        return DB::transaction(
            function () use (
                $uuid,
                $data
            ): PurchaseOrder {
                $purchaseOrder = $this->getRequired(
                    $uuid
                );

                $this->ensureEditable(
                    $purchaseOrder
                );

                $this->validateReferences($data);

                $items = $data['items'] ?? [];

                unset(
                    $data['items'],
                    $data['order_number'],
                    $data['status']
                );

                $calculatedItems = $this->calculateItems(
                    $items
                );

                $totals = $this->calculateOrderTotals(
                    $calculatedItems,
                    $data,
                    $purchaseOrder
                );

                $data = array_merge(
                    $data,
                    $totals
                );

                $this->repository->update(
                    $purchaseOrder,
                    $data
                );

                $this->itemRepository->deleteByPurchaseOrder(
                    $purchaseOrder->id
                );

                $this->itemRepository->createMany(
                    $purchaseOrder->id,
                    $calculatedItems
                );

                return $this->repository->getWithRelations(
                    $purchaseOrder->uuid
                );
            }
        );
    }

    public function confirm(
        string $uuid
    ): PurchaseOrder {
        return DB::transaction(
            function () use ($uuid): PurchaseOrder {
                $purchaseOrder = $this->getRequired($uuid);

                if (
                    $purchaseOrder->status !==
                    PurchaseOrderStatusEnum::DRAFT->value
                ) {
                    throw new BusinessException(
                        'Only draft purchase orders can be confirmed.',
                        'PURCHASE_ORDER_CONFIRM_NOT_ALLOWED'
                    );
                }

                if (
                    !$this->itemRepository
                        ->findByPurchaseOrder($purchaseOrder->id)
                        ->isNotEmpty()
                ) {
                    throw new BusinessException(
                        'Purchase order must contain at least one item.',
                        'PURCHASE_ORDER_ITEMS_REQUIRED'
                    );
                }

                $this->repository->update(
                    $purchaseOrder,
                    [
                        'status' =>
                            PurchaseOrderStatusEnum::CONFIRMED->value,

                        'confirmed_at' => now(),
                    ]
                );

                return $this->repository->getWithRelations(
                    $purchaseOrder->uuid
                );
            }
        );
    }

    public function cancel(
        string $uuid,
        ?string $reason = null
    ): PurchaseOrder {
        return DB::transaction(
            function () use (
                $uuid,
                $reason
            ): PurchaseOrder {
                $purchaseOrder = $this->getRequired($uuid);

                if (
                    in_array(
                        $purchaseOrder->status,
                        [
                            PurchaseOrderStatusEnum::CLOSED->value,
                            PurchaseOrderStatusEnum::CANCELLED->value,
                        ],
                        true
                    )
                ) {
                    throw new BusinessException(
                        'This purchase order cannot be cancelled.',
                        'PURCHASE_ORDER_CANCEL_NOT_ALLOWED'
                    );
                }

                if (
                    $this->itemRepository
                        ->findByPurchaseOrder($purchaseOrder->id)
                        ->where('received_quantity', '>', 0)
                        ->isNotEmpty()
                ) {
                    throw new BusinessException(
                        'A purchase order with received quantity cannot be cancelled completely.',
                        'PURCHASE_ORDER_HAS_RECEIPTS'
                    );
                }

                $this->repository->update(
                    $purchaseOrder,
                    [
                        'status' =>
                            PurchaseOrderStatusEnum::CANCELLED->value,

                        'cancelled_at' => now(),

                        'cancellation_reason' => $reason,
                    ]
                );

                return $this->repository->getWithRelations(
                    $purchaseOrder->uuid
                );
            }
        );
    }

    public function delete(
        string $uuid
    ): bool {
        return DB::transaction(
            function () use ($uuid): bool {
                $purchaseOrder = $this->getRequired($uuid);

                if (
                    $purchaseOrder->status !==
                    PurchaseOrderStatusEnum::DRAFT->value
                ) {
                    throw new BusinessException(
                        'Only draft purchase orders can be deleted.',
                        'PURCHASE_ORDER_DELETE_NOT_ALLOWED'
                    );
                }

                $this->itemRepository->deleteByPurchaseOrder(
                    $purchaseOrder->id
                );

                return $this->repository->delete(
                    $purchaseOrder
                );
            }
        );
    }

    private function calculateItems(
        array $items
    ): array {
        return array_map(
            fn (array $item): array =>
                $this->itemCalculator->calculate($item),
            $items
        );
    }

    private function calculateOrderTotals(
        array $calculatedItems,
        array $data,
        ?PurchaseOrder $existingOrder = null
    ): array {
        $additionalCharge = (float) (
            $data['additional_charge_amount']
            ?? $existingOrder?->additional_charge_amount
            ?? 0
        );

        $roundingAdjustment = (float) (
            $data['rounding_adjustment']
            ?? $existingOrder?->rounding_adjustment
            ?? 0
        );

        $totals = $this->orderCalculator->calculate(
            $calculatedItems,
            $additionalCharge,
            $roundingAdjustment
        );

        return [
            'subtotal_amount' =>
                $totals['subtotal'],

            'discount_amount' =>
                $totals['discount'],

            'additional_charge_amount' =>
                $totals['additional_charge'],

            'tax_total_amount' =>
                $totals['tax_total'],

            'rounding_adjustment' =>
                $totals['rounding_adjustment'],

            'grand_total_amount' =>
                $totals['grand_total'],
        ];
    }

    private function getRequired(
        string $uuid
    ): PurchaseOrder {
        $purchaseOrder = $this->repository->findByUuid($uuid);

        if (!$purchaseOrder) {
            throw new NotFoundException(
                'Purchase order not found.',
                'PURCHASE_ORDER_NOT_FOUND'
            );
        }

        return $purchaseOrder;
    }

    private function ensureEditable(
        PurchaseOrder $purchaseOrder
    ): void {
        if (
            $purchaseOrder->status !==
            PurchaseOrderStatusEnum::DRAFT->value
        ) {
            throw new BusinessException(
                'Only draft purchase orders can be edited.',
                'PURCHASE_ORDER_EDIT_NOT_ALLOWED'
            );
        }
    }

    private function validateReferences(
        array $data
    ): void {
        if (!empty($data['supplier_id'])) {
            if (
                !$this->supplierRepository->findById(
                    $data['supplier_id']
                )
            ) {
                throw new BusinessException(
                    'Invalid supplier.',
                    'PURCHASE_ORDER_INVALID_SUPPLIER'
                );
            }
        }

        if (!empty($data['warehouse_id'])) {
            if (
                !$this->warehouseRepository->findById(
                    $data['warehouse_id']
                )
            ) {
                throw new BusinessException(
                    'Invalid warehouse.',
                    'PURCHASE_ORDER_INVALID_WAREHOUSE'
                );
            }
        }

        foreach ($data['items'] ?? [] as $item) {
            $productId = $item['product_id'] ?? null;
            $variantId = $item['product_variant_id'] ?? null;

            if ($variantId && !$productId) {
                throw new BusinessException(
                    'Product is required when a product variant is selected.',
                    'PURCHASE_ORDER_PRODUCT_REQUIRED_FOR_VARIANT'
                );
            }

            if ($productId && $variantId) {
                $variants = $this->productVariants->findByProductId(
                    (int) $productId
                );

                $belongsToProduct = $variants->contains(
                    fn ($variant) =>
                        (int) $variant->id === (int) $variantId
                );

                if (!$belongsToProduct) {
                    throw new BusinessException(
                        'The selected product variant does not belong to the selected product.',
                        'PURCHASE_ORDER_INVALID_PRODUCT_VARIANT'
                    );
                }
            }
        }
    }
}