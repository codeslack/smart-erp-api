<?php

namespace App\Modules\PaymentTerm\Services;

use App\Modules\Tenant\Models\Tenant;

use App\Modules\PaymentTerm\Models\PaymentTerm;

use App\Modules\Tenant\Contracts\TenantSetupInterface;

class PaymentTermSetupService implements TenantSetupInterface
{
    public function setup(
        Tenant $tenant
    ): void {

        foreach (
            $this->defaultPaymentTerms()
            as $term
        ) {

            PaymentTerm::query()
                ->firstOrCreate(
                    [
                        'tenant_id' => $tenant->id,
                        'code'      => $term['code'],
                    ],
                    [
                        'name'        => $term['name'],
                        'due_days'    => $term['due_days'],
                        'description' => $term['description'],
                        'is_active'   => true,
                    ]
                );
        }
    }

    protected function defaultPaymentTerms(): array
    {
        return [

            [
                'code'        => 'CASH',
                'name'        => 'Cash',
                'due_days'    => 0,
                'description' => 'Payment due immediately.',
            ],

            [
                'code'        => 'NET7',
                'name'        => 'Net 7 Days',
                'due_days'    => 7,
                'description' => 'Payment due within 7 days.',
            ],

            [
                'code'        => 'NET15',
                'name'        => 'Net 15 Days',
                'due_days'    => 15,
                'description' => 'Payment due within 15 days.',
            ],

            [
                'code'        => 'NET30',
                'name'        => 'Net 30 Days',
                'due_days'    => 30,
                'description' => 'Payment due within 30 days.',
            ],

            [
                'code'        => 'NET45',
                'name'        => 'Net 45 Days',
                'due_days'    => 45,
                'description' => 'Payment due within 45 days.',
            ],

            [
                'code'        => 'NET60',
                'name'        => 'Net 60 Days',
                'due_days'    => 60,
                'description' => 'Payment due within 60 days.',
            ],
        ];
    }
}