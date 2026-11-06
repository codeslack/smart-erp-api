<?php

namespace App\Modules\Accounting\Services\Postings;

use App\Core\Exceptions\BusinessException;
use App\Modules\Accounting\Enums\AccountingAccounts;
use App\Modules\Accounting\Enums\JournalVoucherTypeEnum;
use App\Modules\Accounting\Models\ChartOfAccount;
use App\Modules\Customer\Models\Customer;
use App\Modules\Supplier\Models\Supplier;
use App\Modules\Payment\Enums\PaymentPartyTypeEnum;
use App\Modules\Payment\Enums\PaymentPurposeEnum;
use App\Modules\Payment\Enums\PaymentTypeEnum;
use App\Modules\Payment\Models\Payment;
use App\Modules\Accounting\Services\JournalEntry\JournalEntryService;
use App\Modules\Payment\Services\Validation\Contracts\PaymentAllocationValidatorInterface;

class PaymentAccountingPostingService extends BasePostingService
{
    private const SCALE = 4;

    public function __construct(
        protected JournalEntryService $journalEntryService,
        protected PaymentAllocationValidatorInterface $allocationValidator
    ) {
        parent::__construct($journalEntryService);
    }

    public function post(Payment $payment): void
    {
        $this->validateAmount((string) $payment->amount);

        $purpose = $payment->purpose;

        if (! $purpose instanceof PaymentPurposeEnum) {
            throw new BusinessException('Invalid payment purpose.');
        }

        $this->validatePurposeDirection($payment, $purpose);
        $this->validateParty($payment, $purpose);

        $cashAccount = $this->getPaymentAccount($payment);

        $allocated = '0.0000';

        foreach ($payment->allocations as $allocation) {
            if (! $this->allowsAllocations($purpose)) {
                throw new BusinessException(
                    'Allocations are not allowed for this payment purpose.'
                );
            }

            $this->allocationValidator->validate($payment, $allocation);

            $amount = bcadd(
                (string) $allocation->allocated_amount,
                '0',
                self::SCALE
            );

            $this->validateAmount($amount);

            $allocated = bcadd($allocated, $amount, self::SCALE);
        }

        $paymentAmount = bcadd(
            (string) $payment->amount,
            '0',
            self::SCALE
        );

        if (bccomp($allocated, $paymentAmount, self::SCALE) > 0) {
            throw new BusinessException(
                'Payment allocations cannot exceed payment amount.'
            );
        }

        $remaining = bcsub(
            $paymentAmount,
            $allocated,
            self::SCALE
        );

        $lines = match ($purpose) {
            PaymentPurposeEnum::CUSTOMER_PAYMENT =>
                $this->customerPaymentLines(
                    $cashAccount,
                    $allocated,
                    $remaining,
                    $paymentAmount
                ),

            PaymentPurposeEnum::CUSTOMER_ADVANCE,
            PaymentPurposeEnum::CUSTOMER_OPENING_BALANCE_ADJUSTMENT =>
                [
                    $this->accountDebit($cashAccount, $paymentAmount),
                    $this->credit(
                        AccountingAccounts::CUSTOMER_ADVANCES,
                        $paymentAmount
                    ),
                ],

            PaymentPurposeEnum::CUSTOMER_REFUND =>
                [
                    $this->debit(
                        AccountingAccounts::CUSTOMER_ADVANCES,
                        $paymentAmount
                    ),
                    $this->accountCredit($cashAccount, $paymentAmount),
                ],

            PaymentPurposeEnum::SUPPLIER_PAYMENT =>
                $this->supplierPaymentLines(
                    $cashAccount,
                    $allocated,
                    $remaining,
                    $paymentAmount
                ),

            PaymentPurposeEnum::SUPPLIER_ADVANCE =>
                [
                    $this->debit(
                        AccountingAccounts::SUPPLIER_ADVANCES,
                        $paymentAmount
                    ),
                    $this->accountCredit($cashAccount, $paymentAmount),
                ],

            PaymentPurposeEnum::SUPPLIER_REFUND =>
                [
                    $this->accountDebit($cashAccount, $paymentAmount),
                    $this->credit(
                        AccountingAccounts::SUPPLIER_ADVANCES,
                        $paymentAmount
                    ),
                ],

            PaymentPurposeEnum::OTHER_RECEIPT =>
                $this->otherReceiptLines($payment, $cashAccount, $paymentAmount),

            PaymentPurposeEnum::OTHER_PAYMENT =>
                $this->otherPaymentLines($payment, $cashAccount, $paymentAmount),
        };

        $voucherType = $purpose->isReceipt()
            ? JournalVoucherTypeEnum::RECEIPT
            : JournalVoucherTypeEnum::PAYMENT;

        $this->createJournalEntry(
            entryDate: $payment->payment_date->toDateString(),
            voucherType: $voucherType->value,
            referenceType: Payment::class,
            referenceId: $payment->id,
            description: $purpose->isReceipt()
                ? "Receipt {$payment->payment_no}"
                : "Payment {$payment->payment_no}",
            lines: $lines
        );
    }

    protected function customerPaymentLines(
        ChartOfAccount $cashAccount,
        string $allocated,
        string $remaining,
        string $paymentAmount
    ): array {
        $lines = [
            $this->accountDebit($cashAccount, $paymentAmount),
        ];

        if (bccomp($allocated, '0', self::SCALE) > 0) {
            $lines[] = $this->credit(
                AccountingAccounts::ACCOUNTS_RECEIVABLE,
                $allocated
            );
        }

        if (bccomp($remaining, '0', self::SCALE) > 0) {
            $lines[] = $this->credit(
                AccountingAccounts::CUSTOMER_ADVANCES,
                $remaining
            );
        }

        return $lines;
    }

    protected function supplierPaymentLines(
        ChartOfAccount $cashAccount,
        string $allocated,
        string $remaining,
        string $paymentAmount
    ): array {
        $lines = [];

        if (bccomp($allocated, '0', self::SCALE) > 0) {
            $lines[] = $this->debit(
                AccountingAccounts::ACCOUNTS_PAYABLE,
                $allocated
            );
        }

        if (bccomp($remaining, '0', self::SCALE) > 0) {
            $lines[] = $this->debit(
                AccountingAccounts::SUPPLIER_ADVANCES,
                $remaining
            );
        }

        $lines[] = $this->accountCredit(
            $cashAccount,
            $paymentAmount
        );

        return $lines;
    }

    protected function otherReceiptLines(
        Payment $payment,
        ChartOfAccount $cashAccount,
        string $paymentAmount
    ): array {
        $offsetAccount = $this->getOffsetAccount($payment, $cashAccount);

        return [
            $this->accountDebit($cashAccount, $paymentAmount),
            $this->accountCredit($offsetAccount, $paymentAmount),
        ];
    }

    protected function otherPaymentLines(
        Payment $payment,
        ChartOfAccount $cashAccount,
        string $paymentAmount
    ): array {
        $offsetAccount = $this->getOffsetAccount($payment, $cashAccount);

        return [
            $this->accountDebit($offsetAccount, $paymentAmount),
            $this->accountCredit($cashAccount, $paymentAmount),
        ];
    }

    protected function validatePurposeDirection(
        Payment $payment,
        PaymentPurposeEnum $purpose
    ): void {
        if (
            $purpose->isReceipt()
            && $payment->payment_type !== PaymentTypeEnum::RECEIPT
        ) {
            throw new BusinessException(
                'This payment purpose requires a receipt transaction.'
            );
        }

        if (
            $purpose->isPayment()
            && $payment->payment_type !== PaymentTypeEnum::PAYMENT
        ) {
            throw new BusinessException(
                'This payment purpose requires a payment transaction.'
            );
        }
    }

    protected function validateParty(
        Payment $payment,
        PaymentPurposeEnum $purpose
    ): void {
        if (
            $purpose->isOtherReceipt()
            || $purpose->isOtherPayment()
        ) {
            if (
                $payment->party_type !== PaymentPartyTypeEnum::OTHER
                || $payment->party_id !== null
            ) {
                throw new BusinessException(
                    'Other transactions must not have a customer or supplier.'
                );
            }

            return;
        }

        if (
            $purpose->isCustomerPayment()
            || $purpose->isCustomerAdvance()
            || $purpose->isCustomerOpeningBalanceAdjustment()
            || $purpose->isCustomerRefund()
        ) {
            if (
                $payment->party_type !== PaymentPartyTypeEnum::CUSTOMER
                || ! $payment->party_id
            ) {
                throw new BusinessException(
                    'This payment purpose requires a customer.'
                );
            }

            $exists = Customer::query()
                ->whereKey($payment->party_id)
                ->exists();

            if (! $exists) {
                throw new BusinessException('Customer not found.');
            }

            return;
        }

        if (
            $purpose->isSupplierPayment()
            || $purpose->isSupplierAdvance()
            || $purpose->isSupplierRefund()
        ) {
            if (
                $payment->party_type !== PaymentPartyTypeEnum::SUPPLIER
                || ! $payment->party_id
            ) {
                throw new BusinessException(
                    'This payment purpose requires a supplier.'
                );
            }

            $exists = Supplier::query()
                ->whereKey($payment->party_id)
                ->exists();

            if (! $exists) {
                throw new BusinessException('Supplier not found.');
            }

            return;
        }

        throw new BusinessException('Unsupported payment purpose.');
    }

    protected function allowsAllocations(
        PaymentPurposeEnum $purpose
    ): bool {
        return $purpose->isCustomerPayment()
            || $purpose->isSupplierPayment();
    }

    protected function getPaymentAccount(Payment $payment): ChartOfAccount
    {
        $tenantId = tenantId();

        if (! $tenantId) {
            throw new BusinessException('Active tenant not found.');
        }

        $account = ChartOfAccount::query()
            ->where('tenant_id', $tenantId)
            ->whereKey($payment->account_id)
            ->where('is_active', true)
            ->first();

        if (! $account) {
            throw new BusinessException('Payment account not found.');
        }

        return $account;
    }

    protected function getOffsetAccount(
        Payment $payment,
        ChartOfAccount $cashAccount
    ): ChartOfAccount {
        $tenantId = tenantId();

        if (! $tenantId) {
            throw new BusinessException('Active tenant not found.');
        }

        if (! $payment->offset_account_id) {
            throw new BusinessException(
                'Offset account is required for this transaction.'
            );
        }

        $account = ChartOfAccount::query()
            ->where('tenant_id', $tenantId)
            ->whereKey($payment->offset_account_id)
            ->where('is_active', true)
            ->first();

        if (! $account) {
            throw new BusinessException('Offset account not found.');
        }

        if ($account->is($cashAccount)) {
            throw new BusinessException(
                'Payment account and offset account must be different.'
            );
        }

        return $account;
    }

    protected function accountDebit(
        ChartOfAccount $account,
        string $amount
    ): array {
        return [
            'chart_of_account_uuid' => $account->uuid,
            'debit' => $this->decimal($amount),
            'credit' => '0.0000',
            'description' => null,
        ];
    }

    protected function accountCredit(
        ChartOfAccount $account,
        string $amount
    ): array {
        return [
            'chart_of_account_uuid' => $account->uuid,
            'debit' => '0.0000',
            'credit' => $this->decimal($amount),
            'description' => null,
        ];
    }
}