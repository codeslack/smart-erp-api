<?php

namespace App\Modules\Payment\Services;

use Illuminate\Support\Facades\DB;

use App\Core\Exceptions\BusinessException;
use App\Core\Enums\DocumentStatusEnum;

use App\Modules\Accounting\Enums\JournalVoucherTypeEnum;
use App\Modules\Accounting\Services\AccountingReversalService;

use App\Modules\Payment\Enums\PaymentTypeEnum;
use App\Modules\Payment\Models\Payment;

use App\Modules\Payment\Services\Contracts\PaymentReversalServiceInterface;
use App\Modules\Payment\Services\Contracts\PaymentAllocationReversalServiceInterface;

class PaymentReversalService
    implements PaymentReversalServiceInterface
{
    public function __construct(
        protected AccountingReversalService $accountingReversalService,
        protected PaymentAllocationReversalServiceInterface $allocationReversalService,
    ) {}

    public function reverse(
        Payment $payment
    ): Payment {
        return DB::transaction(
            function () use ($payment) {

                $payment = Payment::query()
                    ->whereKey($payment->id)
                    ->lockForUpdate()
                    ->first();

                if (! $payment) {
                    throw new BusinessException(
                        'Payment not found.'
                    );
                }

                if (
                    $payment->status
                    !== DocumentStatusEnum::CONFIRMED->value
                ) {
                    throw new BusinessException(
                        'Only confirmed payments can be reversed.'
                    );
                }

                $payment->load([
                    'allocations',
                ]);

                $voucherType =
                    $payment->payment_type
                    === PaymentTypeEnum::RECEIPT
                        ? JournalVoucherTypeEnum::RECEIPT->value
                        : JournalVoucherTypeEnum::PAYMENT->value;

                /*
                 * Reverse the original accounting entry.
                 */
                $this->accountingReversalService
                    ->reverseByReference(
                        referenceType: Payment::class,
                        referenceId: $payment->id,
                        voucherType: $voucherType,
                    );

                /*
                 * Restore opening-bill balances.
                 *
                 * If this fails, the accounting reversal is
                 * rolled back by the surrounding transaction.
                 */
                $this->allocationReversalService->reverse(
                    $payment
                );

                $payment->update([
                    'status' => DocumentStatusEnum::REVERSED->value,
                ]);

                return $payment->fresh([
                    'allocations',
                ]);
            }
        );
    }
}