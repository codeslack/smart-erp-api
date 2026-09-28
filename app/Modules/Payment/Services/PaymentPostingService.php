<?php

namespace App\Modules\Payment\Services;

use Illuminate\Support\Facades\DB;

use App\Core\Exceptions\BusinessException;
use App\Core\Enums\DocumentStatusEnum;

use App\Modules\Payment\Models\Payment;

use App\Modules\Accounting\Services\Contracts\AccountingPostingServiceInterface;

use App\Modules\Payment\Services\Contracts\PaymentPostingServiceInterface;

class PaymentPostingService
    implements PaymentPostingServiceInterface
{
    public function __construct(
        protected AccountingPostingServiceInterface $accountingPostingService,
    ) {}

    public function confirm(
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
                    !== DocumentStatusEnum::DRAFT->value
                ) {
                    throw new BusinessException(
                        'Only draft payments can be confirmed.'
                    );
                }

                $payment->load([
                    'allocations',
                ]);

                $this->accountingPostingService
                    ->postPayment($payment);

                $payment->update([
                    'status' => DocumentStatusEnum::CONFIRMED->value,
                ]);

                return $payment->fresh([
                    'allocations',
                ]);
            }
        );
    }
}