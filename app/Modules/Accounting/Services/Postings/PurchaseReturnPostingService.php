<?php

namespace App\Modules\Accounting\Services\Postings;

use App\Modules\Accounting\Enums\AccountingAccounts;
use App\Modules\Accounting\Enums\JournalEntryStatusEnum;
use App\Modules\Accounting\Enums\JournalVoucherTypeEnum;
use App\Modules\PurchaseReturn\Enums\PurchaseReturnRefundType;
use App\Modules\PurchaseReturn\Models\PurchaseReturn;

class PurchaseReturnPostingService extends BasePostingService
{
    public function post(PurchaseReturn $purchaseReturn): void
    {
        $grandTotal = $this->decimal($purchaseReturn->grand_total);
        $this->validateAmount($grandTotal);

        $purchaseReturn->loadMissing(['refundAccount']);

        $taxAmount = (float) $purchaseReturn->tax_amount;
        $inventoryAmount = max(0, (float) $purchaseReturn->grand_total - $taxAmount);

        $lines = [];

        // Debit: Accounts Payable (for credit notes) or Refund Account (Cash/Bank)
        if ($purchaseReturn->refund_type !== PurchaseReturnRefundType::CREDIT_NOTE && $purchaseReturn->refundAccount) {
            $lines[] = [
                'chart_of_account_uuid' => $purchaseReturn->refundAccount->uuid,
                'debit' => $grandTotal,
                'credit' => '0.0000',
                'description' => "Purchase Return Refund {$purchaseReturn->return_no}",
            ];
        } else {
            $lines[] = $this->debit(
                AccountingAccounts::ACCOUNTS_PAYABLE,
                $grandTotal,
                "Purchase Return {$purchaseReturn->return_no}"
            );
        }

        // Credit: Inventory for goods cost returned
        if ($inventoryAmount > 0) {
            $lines[] = $this->credit(
                AccountingAccounts::INVENTORY,
                $this->decimal($inventoryAmount),
                "Purchase Return Inventory {$purchaseReturn->return_no}"
            );
        }

        // Credit: Input Tax Receivable for reversed tax
        if ($taxAmount > 0) {
            $lines[] = $this->credit(
                AccountingAccounts::INPUT_TAX_RECEIVABLE,
                $this->decimal($taxAmount),
                "Purchase Return Tax {$purchaseReturn->return_no}"
            );
        }

        $entryDate = $purchaseReturn->return_date instanceof \DateTimeInterface
            ? $purchaseReturn->return_date->format('Y-m-d')
            : (string) $purchaseReturn->return_date;

        $entry = $this->journalEntryService->createAndPost([
            'voucher_type' => JournalVoucherTypeEnum::PURCHASE_RETURN->value,
            'reference_type' => PurchaseReturn::class,
            'reference_id' => $purchaseReturn->id,
            'entry_date' => $entryDate,
            'description' => "Purchase Return {$purchaseReturn->return_no}",
            'status' => JournalEntryStatusEnum::DRAFT,
            'created_by' => auth()->id() ?? userId(),
            'lines' => $lines,
        ]);

        if ($entry) {
            $purchaseReturn->update([
                'journal_entry_id' => $entry->id,
            ]);
        }
    }
}
