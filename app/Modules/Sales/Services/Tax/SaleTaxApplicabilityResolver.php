<?php

namespace App\Modules\Sales\Services\Tax;

use App\Modules\Customer\Models\Customer;
use App\Modules\Sales\Models\Sale;
use App\Modules\Settings\Models\Setting;
use App\Modules\Tax\Domain\TaxApplicabilityInput;
use App\Modules\Tax\Domain\TaxApplicabilityResult;
use App\Modules\Tax\Enums\TaxDirectionEnum;
use App\Modules\Tax\Enums\TaxUsageEnum;
use App\Modules\Tax\Models\TaxRegistration;
use App\Modules\Tax\Services\TaxApplicabilityService;
use App\Modules\Tenant\Models\Tenant;

class SaleTaxApplicabilityResolver
{
    public function __construct(
        protected TaxApplicabilityService $applicabilityService,
    ) {
    }

    public function resolve(Sale $sale): TaxApplicabilityResult
    {
        $tenant = tenant();
        $customer = $sale->customer;

        $calculationDate = $sale->sale_date->toDateString();

        $sourceRegistration = $this->activeRegistration(
            $tenant,
            $calculationDate,
        );

        $destinationRegistration = $customer
            ? $this->activeRegistration($customer, $calculationDate)
            : null;

        $sourceCountry = $sourceRegistration?->country_code
            ?? $this->companySetting('country');

        $sourceState = $sourceRegistration?->state_code
            ?? $this->companySetting('state_code');

        $destinationCountry = $destinationRegistration?->country_code
            ?? $customer?->country_code;

        $destinationState = $destinationRegistration?->state_code
            ?? $customer?->state_code;

        return $this->applicabilityService->resolve(
            new TaxApplicabilityInput(
                usage: TaxUsageEnum::SALES,
                direction: TaxDirectionEnum::OUTPUT,
                sourceCountryCode: $sourceCountry,
                sourceStateCode: $sourceState,
                destinationCountryCode: $destinationCountry,
                destinationStateCode: $destinationState,
                placeOfSupplyStateCode: $destinationState,
                sourceRegistrationRequired: $sourceRegistration !== null,
                destinationRegistrationRequired: $destinationRegistration !== null,
                calculationDate: $calculationDate,
            ),
        );
    }

    protected function activeRegistration(
        Tenant|Customer $registrable,
        string $date,
    ): ?TaxRegistration {
        return TaxRegistration::query()
            ->where('registrable_type', $registrable->getMorphClass())
            ->where('registrable_id', $registrable->getKey())
            ->where('is_active', true)
            ->where('is_primary', true)
            ->whereDate('effective_from', '<=', $date)
            ->where(function ($query) use ($date) {
                $query->whereNull('effective_to')
                    ->orWhereDate('effective_to', '>=', $date);
            })
            ->orderByDesc('effective_from')
            ->first();
    }

    protected function companySetting(string $key): ?string
    {
        $value = Setting::query()
            ->where('group', Setting::GROUP_COMPANY)
            ->where('key', $key)
            ->value('value');

        return $value !== null ? (string) $value : null;
    }
}
