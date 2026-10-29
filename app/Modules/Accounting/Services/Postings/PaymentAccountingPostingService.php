<?php

namespace App\Modules\Accounting\Services\Postings;

use App\Core\Exceptions\BusinessException;
use App\Modules\Accounting\Enums\AccountingAccounts;
use App\Modules\Accounting\Enums\JournalVoucherTypeEnum;
use App\Modules\Accounting\Models\ChartOfAccount;
use App\Modules\Customer\Models\Customer;
use App\Modules\Supplier\Models\Supplier;
use App\Modules\Payment\Enums\PaymentPartyTypeEnum;
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
        $this->validateParty($payment);

        $cashAccount = $this->getPaymentAccount($payment);
        $allocated = '0.0000';

        foreach ($payment->allocations as $allocation) {
            $this->allocationValidator->validate($payment, $allocation);

            $amount = bcadd((string) $allocation->allocated_amount, '0', self::SCALE);
            $this->validateAmount($amount);

            $allocated = bcadd($allocated, $amount, self::SCALE);
        }

        $paymentAmount = bcadd((string) $payment->amount, '0', self::SCALE);

        if (bccomp($allocated, $paymentAmount, self::SCALE) > 0) {
            throw new BusinessException('Payment allocations cannot exceed payment amount.');
        }

        $remaining = bcsub($paymentAmount, $allocated, self::SCALE);

        if ($payment->payment_type === PaymentTypeEnum::RECEIPT) {
            $this->postReceipt($payment, $cashAccount, $allocated, $remaining, $paymentAmount);

            return;
        }

        if ($payment->payment_type === PaymentTypeEnum::PAYMENT) {
            $this->postPayment($payment, $cashAccount, $allocated, $remaining, $paymentAmount);

            return;
        }

        throw new BusinessException('Unsupported payment type for accounting posting.');
    }

    protected function postReceipt(
        Payment $payment,
        ChartOfAccount $cashAccount,
        string $allocated,
        string $remaining,
        string $paymentAmount
    ): void {
        $lines = [
            $this->accountDebit($cashAccount, $paymentAmount),
        ];

        if (bccomp($allocated, '0', self::SCALE) > 0) {
            $lines[] = $this->credit(AccountingAccounts::ACCOUNTS_RECEIVABLE, $allocated);
        }

        if (bccomp($remaining, '0', self::SCALE) > 0) {
            $lines[] = $this->credit(AccountingAccounts::CUSTOMER_ADVANCES, $remaining);
        }

        $this->createJournalEntry(
            entryDate: $payment->payment_date->toDateString(),
            voucherType: JournalVoucherTypeEnum::RECEIPT->value,
            referenceType: Payment::class,
            referenceId: $payment->id,
            description: "Receipt {$payment->payment_no}",
            lines: $lines
        );
    }

    protected function postPayment(
        Payment $payment,
        ChartOfAccount $cashAccount,
        string $allocated,
        string $remaining,
        string $paymentAmount
    ): void {
        $lines = [];

        if (bccomp($allocated, '0', self::SCALE) > 0) {
            $lines[] = $this->debit(AccountingAccounts::ACCOUNTS_PAYABLE, $allocated);
        }

        if (bccomp($remaining, '0', self::SCALE) > 0) {
            $lines[] = $this->debit(AccountingAccounts::SUPPLIER_ADVANCES, $remaining);
        }

        $lines[] = $this->accountCredit($cashAccount, $paymentAmount);

        $this->createJournalEntry(
            entryDate: $payment->payment_date->toDateString(),
            voucherType: JournalVoucherTypeEnum::PAYMENT->value,
            referenceType: Payment::class,
            referenceId: $payment->id,
            description: "Payment {$payment->payment_no}",
            lines: $lines
        );
    }

    protected function validateParty(Payment $payment): void
    {
        if (
            $payment->payment_type === PaymentTypeEnum::RECEIPT
            && $payment->party_type !== PaymentPartyTypeEnum::CUSTOMER
        ) {
            throw new BusinessException('Receipt must belong to a customer.');
        }

        if (
            $payment->payment_type === PaymentTypeEnum::PAYMENT
            && $payment->party_type !== PaymentPartyTypeEnum::SUPPLIER
        ) {
            throw new BusinessException('Payment must belong to a supplier.');
        }

        if ($payment->party_type === PaymentPartyTypeEnum::CUSTOMER) {
            $exists = Customer::query()->whereKey($payment->party_id)->exists();

            if (! $exists) {
                throw new BusinessException('Customer not found.');
            }

            return;
        }

        if ($payment->party_type === PaymentPartyTypeEnum::SUPPLIER) {
            $exists = Supplier::query()->whereKey($payment->party_id)->exists();

            if (! $exists) {
                throw new BusinessException('Supplier not found.');
            }

            return;
        }

        throw new BusinessException('Unsupported payment party.');
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

    protected function accountDebit(ChartOfAccount $account, string $amount): array
    {
        return [
            'chart_of_account_uuid' => $account->uuid,
            'debit' => $this->decimal($amount),
            'credit' => '0.0000',
            'description' => null,
        ];
    }

    protected function accountCredit(ChartOfAccount $account, string $amount): array
    {
        return [
            'chart_of_account_uuid' => $account->uuid,
            'debit' => '0.0000',
            'credit' => $this->decimal($amount),
            'description' => null,
        ];
    }
}
