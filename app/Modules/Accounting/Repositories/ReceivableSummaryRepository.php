<?php

namespace App\Modules\Accounting\Repositories;

use App\Modules\Customer\Models\Customer;
use App\Modules\Accounting\Repositories\Contracts\ReceivableSummaryRepositoryInterface;

class ReceivableSummaryRepository implements ReceivableSummaryRepositoryInterface
{
    public function getReport(): array
    {
        return Customer::query()
            ->withSum('sales as total_sales', 'grand_total')
            ->withSum('sales as total_received', 'paid_amount')
            ->withSum('sales as outstanding', 'due_amount')
            ->get()
            ->map(function (Customer $customer) {
                return [
                    'customer_id' => $customer->id,
                    'customer_uuid' => $customer->uuid,
                    'customer_name' => $customer->name,
                    'total_sales' => (float) ($customer->total_sales ?? 0),
                    'total_received' => (float) ($customer->total_received ?? 0),
                    'outstanding' => (float) ($customer->outstanding ?? 0),
                ];
            })
            ->filter(function ($row) {
                return $row['total_sales'] > 0
                    || $row['total_received'] > 0
                    || $row['outstanding'] > 0;
            })
            ->values()
            ->toArray();
    }
}
