<?php

namespace Tests\Feature\Tax;

use Tests\TestCase;

use Tests\Support\CreatesTenant;

use App\Core\Exceptions\BusinessException;

use App\Modules\Tenant\Models\Tenant;
use App\Modules\Customer\Models\Customer;

use App\Modules\Tax\Enums\TaxRegistrationTypeEnum;
use App\Modules\Tax\Models\TaxRegistration;
use App\Modules\Tax\Services\TaxRegistrationService;

use Illuminate\Foundation\Testing\RefreshDatabase;

class TaxRegistrationServiceTest extends TestCase
{
    use RefreshDatabase;
    use CreatesTenant;

    protected Tenant $tenant;

    protected TaxRegistrationService $service;

    protected Customer $customer;

    protected Customer $secondCustomer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = $this->createTestTenant();

        $this->customer = Customer::create([
            'tenant_id' => $this->tenant->id,
            'code' => 'TAX-SVC-CUS-1',
            'name' => 'Tax Service Customer',
            'is_active' => true,
        ]);

        $this->secondCustomer = Customer::create([
            'tenant_id' => $this->tenant->id,
            'code' => 'TAX-SVC-CUS-2',
            'name' => 'Second Tax Service Customer',
            'is_active' => true,
        ]);

        $this->service = app(
            TaxRegistrationService::class
        );
    }

    public function test_registration_can_be_created(): void
    {
        $registration = $this->service->create([
            'registrable_type' => 'customer',
            'registrable_id' => $this->customer->id,
            'registration_type' =>
                TaxRegistrationTypeEnum::GSTIN->value,
            'registration_number' => '22AAAAA0000A1Z5',
            'country_code' => 'IN',
            'state_code' => 'WB',
            'is_primary' => true,
            'is_active' => true,
            'effective_from' => '2026-01-01',
            'effective_to' => null,
        ]);

        $this->assertInstanceOf(
            TaxRegistration::class,
            $registration
        );

        $this->assertDatabaseHas(
            'tax_registrations',
            [
                'tenant_id' => $this->tenant->id,
                'registrable_type' => 'customer',
                'registrable_id' => $this->customer->id,
                'registration_type' =>
                    TaxRegistrationTypeEnum::GSTIN->value,
                'registration_number' => '22AAAAA0000A1Z5',
                'is_primary' => true,
                'is_active' => true,
            ]
        );
    }

    public function test_registration_number_is_trimmed(): void
    {
        $registration = $this->service->create([
            'registrable_type' => 'customer',
            'registrable_id' => $this->customer->id,
            'registration_type' =>
                TaxRegistrationTypeEnum::GSTIN->value,
            'registration_number' => ' 22AAAAA0000A1Z5 ',
            'is_primary' => false,
            'is_active' => true,
            'effective_from' => '2026-01-01',
        ]);

        $this->assertSame(
            '22AAAAA0000A1Z5',
            $registration->registration_number
        );
    }

    public function test_invalid_registration_type_is_rejected(): void
    {
        $this->expectException(BusinessException::class);

        $this->service->create([
            'registrable_type' => 'customer',
            'registrable_id' => $this->customer->id,
            'registration_type' => 'INVALID',
            'registration_number' => '22AAAAA0000A1Z5',
            'is_primary' => false,
            'is_active' => true,
            'effective_from' => '2026-01-01',
        ]);
    }

    public function test_invalid_gstin_is_rejected(): void
    {
        $this->expectException(BusinessException::class);

        $this->service->create([
            'registrable_type' => 'customer',
            'registrable_id' => $this->customer->id,
            'registration_type' =>
                TaxRegistrationTypeEnum::GSTIN->value,
            'registration_number' => 'INVALID-GSTIN',
            'is_primary' => false,
            'is_active' => true,
            'effective_from' => '2026-01-01',
        ]);
    }

    public function test_invalid_effective_period_is_rejected(): void
    {
        $this->expectException(BusinessException::class);

        $this->service->create([
            'registrable_type' => 'customer',
            'registrable_id' => $this->customer->id,
            'registration_type' =>
                TaxRegistrationTypeEnum::GSTIN->value,
            'registration_number' => '22AAAAA0000A1Z5',
            'is_primary' => false,
            'is_active' => true,
            'effective_from' => '2026-12-31',
            'effective_to' => '2026-01-01',
        ]);
    }

    public function test_primary_registration_requires_active_status(): void
    {
        $this->expectException(BusinessException::class);

        $this->service->create([
            'registrable_type' => 'customer',
            'registrable_id' => $this->customer->id,
            'registration_type' =>
                TaxRegistrationTypeEnum::GSTIN->value,
            'registration_number' => '22AAAAA0000A1Z5',
            'is_primary' => true,
            'is_active' => false,
            'effective_from' => '2026-01-01',
        ]);
    }

    public function test_duplicate_registration_number_is_rejected(): void
    {
        $this->service->create([
            'registrable_type' => 'customer',
            'registrable_id' => $this->customer->id,
            'registration_type' =>
                TaxRegistrationTypeEnum::GSTIN->value,
            'registration_number' => '22AAAAA0000A1Z5',
            'is_primary' => false,
            'is_active' => true,
            'effective_from' => '2026-01-01',
        ]);

        $this->expectException(BusinessException::class);

        $this->service->create([
            'registrable_type' => 'customer',
            'registrable_id' => $this->secondCustomer->id,
            'registration_type' =>
                TaxRegistrationTypeEnum::GSTIN->value,
            'registration_number' => '22AAAAA0000A1Z5',
            'is_primary' => false,
            'is_active' => true,
            'effective_from' => '2026-01-01',
        ]);
    }

    public function test_only_one_primary_registration_is_allowed_per_registrable(): void
    {
        $this->service->create([
            'registrable_type' => 'customer',
            'registrable_id' => $this->customer->id,
            'registration_type' =>
                TaxRegistrationTypeEnum::GSTIN->value,
            'registration_number' => '22AAAAA0000A1Z5',
            'is_primary' => true,
            'is_active' => true,
            'effective_from' => '2026-01-01',
        ]);

        $this->expectException(BusinessException::class);

        $this->service->create([
            'registrable_type' => 'customer',
            'registrable_id' => $this->customer->id,
            'registration_type' =>
                TaxRegistrationTypeEnum::GSTIN->value,
            'registration_number' => '19BBBBB1111B2Z6',
            'is_primary' => true,
            'is_active' => true,
            'effective_from' => '2026-01-01',
        ]);
    }

    public function test_different_registrables_can_have_primary_registrations(): void
    {
        $first = $this->service->create([
            'registrable_type' => 'customer',
            'registrable_id' => $this->customer->id,
            'registration_type' =>
                TaxRegistrationTypeEnum::GSTIN->value,
            'registration_number' => '22AAAAA0000A1Z5',
            'is_primary' => true,
            'is_active' => true,
            'effective_from' => '2026-01-01',
        ]);

        $second = $this->service->create([
            'registrable_type' => 'customer',
            'registrable_id' => $this->secondCustomer->id,
            'registration_type' =>
                TaxRegistrationTypeEnum::GSTIN->value,
            'registration_number' => '19BBBBB1111B2Z6',
            'is_primary' => true,
            'is_active' => true,
            'effective_from' => '2026-01-01',
        ]);

        $this->assertTrue(
            $first->is_primary
        );

        $this->assertTrue(
            $second->is_primary
        );
    }

    public function test_update_ignores_current_registration(): void
    {
        $registration = $this->service->create([
            'registrable_type' => 'customer',
            'registrable_id' => $this->customer->id,
            'registration_type' =>
                TaxRegistrationTypeEnum::GSTIN->value,
            'registration_number' => '22AAAAA0000A1Z5',
            'is_primary' => true,
            'is_active' => true,
            'effective_from' => '2026-01-01',
        ]);

        $updated = $this->service->update(
            $registration,
            [
                'registration_number' =>
                    '22AAAAA0000A1Z5',
                'is_primary' => true,
            ]
        );

        $this->assertSame(
            $registration->id,
            $updated->id
        );

        $this->assertSame(
            '22AAAAA0000A1Z5',
            $updated->registration_number
        );

        $this->assertTrue(
            $updated->is_primary
        );
    }

    public function test_registration_can_be_updated(): void
    {
        $registration = $this->service->create([
            'registrable_type' => 'customer',
            'registrable_id' => $this->customer->id,
            'registration_type' =>
                TaxRegistrationTypeEnum::GSTIN->value,
            'registration_number' => '22AAAAA0000A1Z5',
            'state_code' => 'WB',
            'is_primary' => false,
            'is_active' => true,
            'effective_from' => '2026-01-01',
        ]);

        $updated = $this->service->update(
            $registration,
            [
                'registration_number' =>
                    '22AAAAA0000A1Z5',
                'state_code' => 'MH',
            ]
        );

        $this->assertSame(
            'MH',
            $updated->state_code
        );
    }

    public function test_registration_can_be_deleted(): void
    {
        $registration = $this->service->create([
            'registrable_type' => 'customer',
            'registrable_id' => $this->customer->id,
            'registration_type' =>
                TaxRegistrationTypeEnum::GSTIN->value,
            'registration_number' => '22AAAAA0000A1Z5',
            'is_primary' => false,
            'is_active' => true,
            'effective_from' => '2026-01-01',
        ]);

        $result = $this->service->delete(
            $registration
        );

        $this->assertTrue($result);

        $this->assertSoftDeleted(
            'tax_registrations',
            [
                'id' => $registration->id,
            ]
        );
    }
}