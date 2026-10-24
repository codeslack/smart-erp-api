<?php

namespace App\Modules\Inventory\Services;

use Illuminate\Support\Facades\DB;

use App\Core\Exceptions\BusinessException;

use App\Modules\Inventory\Data\InventoryReversalData;

use App\Modules\Inventory\Repositories\Contracts\StockLedgerRepositoryInterface;

use App\Modules\Inventory\Services\Reversal\InventoryStockInReversalService;
use App\Modules\Inventory\Services\Reversal\InventoryStockOutReversalService;

class InventoryReversalService
{
    public function __construct(
        protected StockLedgerRepositoryInterface $stockLedgerRepository,

        protected InventoryStockInReversalService $stockInReversalService,

        protected InventoryStockOutReversalService $stockOutReversalService,
    ) {}

    public function reverse(
        InventoryReversalData $data
    ) {
        return DB::transaction(
            function () use ($data) {
                $ledger = $this->stockLedgerRepository
                    ->findForUpdate($data->stockLedgerId);

                if (! $ledger) {
                    throw new BusinessException(
                        'Stock ledger not found.',
                        'LEDGER_NOT_FOUND'
                    );
                }

                if ($ledger->reversal_of_ledger_id) {
                    throw new BusinessException(
                        'Reversal ledger cannot be reversed.',
                        'INVALID_REVERSAL'
                    );
                }

                if ($ledger->reversal()->exists()) {
                    throw new BusinessException(
                        'Ledger already reversed.',
                        'LEDGER_ALREADY_REVERSED'
                    );
                }

                if ((float) $ledger->quantity_in > 0) {
                    return $this->stockInReversalService->reverse(
                        $ledger,
                        $data
                    );
                }

                if ((float) $ledger->quantity_out > 0) {
                    return $this->stockOutReversalService->reverse(
                        $ledger,
                        $data
                    );
                }

                throw new BusinessException(
                    'Stock ledger has no quantity to reverse.',
                    'INVALID_REVERSAL_QUANTITY'
                );
            }
        );
    }
}