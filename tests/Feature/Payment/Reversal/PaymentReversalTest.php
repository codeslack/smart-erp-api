<?php

namespace Tests\Feature\Payment\Reversal;

use Tests\TestCase;
use Tests\Support\CreatesTenant;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

use Laravel\Sanctum\Sanctum;

use App\Core\Enums\DocumentStatusEnum;
use App\Core\Enums\OpeningBalanceTypeEnum;
use App\Core\Tenant\TenantManager;

use App\Modules\Tenant\Models\Tenant;
use App\Modules\User\Models\User;
use App\Modules\Supplier\Models\Supplier;
use App\Modules\SupplierOpeningBill\Models\SupplierOpeningBill;
use App\Modules\Payment\Models\Payment;
use App\Modules\Payment\Enums\PaymentMethodEnum;
use App\Modules\Payment\Enums\PaymentPartyTypeEnum;
use App\Modules\Payment\Enums\PaymentPurposeEnum;
use App\Modules\Payment\Enums\PaymentTypeEnum;
use App\Modules\Accounting\Enums\AccountingAccounts;
use App\Modules\Accounting\Models\ChartOfAccount;
use App\Modules\Accounting\Services\AccountingSetupService;

class PaymentReversalTest extends TestCase
{
    use RefreshDatabase;
    use CreatesTenant;

    protected Tenant $tenant;
    protected User $user;
    protected ChartOfAccount $cashAccount;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = $this->createTestTenant();

        app(TenantManager::class)
            ->setTenant($this->tenant);

        app(AccountingSetupService::class)
            ->setup($this->tenant);

        $this->cashAccount = ChartOfAccount::query()
            ->where('tenant_id', $this->tenant->id)
            ->where(
                'account_code',
                AccountingAccounts::CASH
            )
            ->firstOrFail();

        $this->user = User::query()->create([
            'uuid' => (string) Str::uuid(),
            'code' => 'TEST-USER',
            'tenant_id' => $this->tenant->id,
            'name' => 'Test User',
            'email' => 'test-' . uniqid() . '@example.com',
            'password' => 'password',
            'is_active' => true,
        ]);

        Sanctum::actingAs($this->user);
    }

    protected function createSupplier(): Supplier
    {
        return Supplier::query()->create([
            'name' => 'Test Supplier ' . uniqid(),
            'code' => 'SUP-' . uniqid(),
            'is_active' => true,
        ]);
    }

    public function test_payment_can_be_confirmed_and_reversed_through_api(): void
    {
        $supplier = $this->createSupplier();

        $bill = SupplierOpeningBill::query()->create([
            'tenant_id' => $this->tenant->id,
            'uuid' => (string) Str::uuid(),
            'supplier_id' => $supplier->id,
            'bill_no' => 'OB-REV-001',
            'bill_date' => '2026-01-01',
            'due_date' => '2026-01-31',
            'amount' => 10000,
            'balance_amount' => 10000,
            'balance_type' => OpeningBalanceTypeEnum::CREDIT,
            'notes' => 'Payment reversal API test',
        ]);

        $createResponse = $this->postJson(
            route('payments.store'),
            [
                'payment_no' => 'PAY-REV-' . uniqid(),
                'payment_type' => PaymentTypeEnum::PAYMENT,
                'purpose' => PaymentPurposeEnum::SUPPLIER_PAYMENT,
                'payment_date' => '2026-09-28',
                'party_type' =>
                PaymentPartyTypeEnum::SUPPLIER->value,
                'party_id' => $supplier->id,
                'account_id' => $this->cashAccount->id,
                'amount' => 6000,
                'payment_method' =>
                PaymentMethodEnum::CASH->value,
                'reference_no' => null,
                'notes' => 'Payment reversal API test',
                'allocations' => [
                    [
                        'document_type' =>
                        'supplier_opening_bill',
                        'document_id' => $bill->id,
                        'allocated_amount' => 6000,
                    ],
                ],
            ]
        );

        $createResponse->assertCreated();

        app(\App\Core\Tenant\TenantManager::class)
            ->setTenant($this->tenant);

        $payment = Payment::query()
            ->where(
                'payment_no',
                $createResponse->json(
                    'data.payment_no'
                )
            )
            ->firstOrFail();

        $this->assertSame(
            DocumentStatusEnum::DRAFT->value,
            $payment->status
        );

        $confirmResponse = $this->postJson(
            route(
                'payments.confirm',
                $payment
            )
        );

        $confirmResponse->assertSuccessful();

        $payment->refresh();
        $bill->refresh();

        $this->assertSame(
            DocumentStatusEnum::CONFIRMED->value,
            $payment->status
        );

        $this->assertEquals(
            '4000.0000',
            $bill->balance_amount
        );

        $reverseResponse = $this->postJson(
            route(
                'payments.reverse',
                $payment
            )
        );

        $reverseResponse->assertSuccessful();

        $payment->refresh();
        $bill->refresh();

        $this->assertSame(
            DocumentStatusEnum::REVERSED->value,
            $payment->status
        );

        $this->assertEquals(
            '10000.0000',
            $bill->balance_amount
        );
    }

    public function test_reversed_payment_cannot_be_reversed_again(): void
    {
        $supplier = $this->createSupplier();

        $bill = SupplierOpeningBill::query()->create([
            'tenant_id' => $this->tenant->id,
            'uuid' => (string) Str::uuid(),
            'supplier_id' => $supplier->id,
            'bill_no' => 'OB-REV-002',
            'bill_date' => '2026-01-01',
            'due_date' => '2026-01-31',
            'amount' => 10000,
            'balance_amount' => 10000,
            'balance_type' => OpeningBalanceTypeEnum::CREDIT,
            'notes' => 'Double reversal test',
        ]);

        $createResponse = $this->postJson(
            route('payments.store'),
            [
                'payment_no' => 'PAY-REV-002-' . uniqid(),
                'payment_type' => PaymentTypeEnum::PAYMENT,
                'purpose' => PaymentPurposeEnum::SUPPLIER_PAYMENT,
                'payment_date' => '2026-09-28',
                'party_type' =>
                PaymentPartyTypeEnum::SUPPLIER->value,
                'party_id' => $supplier->id,
                'account_id' => $this->cashAccount->id,
                'amount' => 6000,
                'payment_method' =>
                PaymentMethodEnum::CASH->value,
                'reference_no' => null,
                'notes' => 'Double reversal test',
                'allocations' => [
                    [
                        'document_type' =>
                        'supplier_opening_bill',
                        'document_id' => $bill->id,
                        'allocated_amount' => 6000,
                    ],
                ],
            ]
        );

        $createResponse->assertCreated();

        app(\App\Core\Tenant\TenantManager::class)
            ->setTenant($this->tenant);

        $payment = Payment::query()
            ->where(
                'payment_no',
                $createResponse->json(
                    'data.payment_no'
                )
            )
            ->firstOrFail();

        $this->postJson(
            route('payments.confirm', $payment)
        )->assertSuccessful();

        $this->postJson(
            route('payments.reverse', $payment)
        )->assertSuccessful();

        $bill->refresh();

        $this->assertEquals(
            '10000.0000',
            $bill->balance_amount
        );

        $payment->refresh();

        $this->assertSame(
            DocumentStatusEnum::REVERSED->value,
            $payment->status
        );

        $secondReverseResponse = $this->postJson(
            route('payments.reverse', $payment)
        );

        $secondReverseResponse->assertStatus(422);

        $secondReverseResponse->assertJson([
            'success' => false,
        ]);

        $bill->refresh();
        $payment->refresh();

        $this->assertEquals(
            '10000.0000',
            $bill->balance_amount
        );

        $this->assertSame(
            DocumentStatusEnum::REVERSED->value,
            $payment->status
        );
    }
}
