<?php

namespace App\Modules\Customer\Services;

use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

use App\Core\Exceptions\BusinessException;

use App\Core\Services\BaseService;

use App\Modules\Customer\Models\Customer;

use App\Modules\CustomerOpeningBill\Services\CustomerOpeningBillService;
use App\Modules\CustomerOpeningBill\Services\CustomerOpeningBillPostingService;

use App\Modules\Customer\Repositories\Contracts\CustomerRepositoryInterface;

class CustomerService extends BaseService
{
    public function __construct(
        protected CustomerRepositoryInterface $repository,
        protected CustomerOpeningBillService $openingBills,
        protected CustomerOpeningBillPostingService $openingBillPosting,
    ) {}

    public function paginate(
        int $perPage = 15
    ): LengthAwarePaginator {
        return $this->repository
            ->paginate($perPage);
    }

    public function create(
        array $data
    ): Customer {
        return DB::transaction(
            function () use ($data) {

                $openingBills =
                    $data['opening_bills'] ?? [];

                unset(
                    $data['opening_bills']
                );

                $customer =
                    $this->repository
                        ->create($data);

                $this->createOpeningBills(
                    $customer,
                    $openingBills
                );

                $this->openingBillPosting
                    ->post($customer);

                return $customer->refresh();
            }
        );
    }

    public function update(
        Customer $customer,
        array $data
    ): Customer {
        return DB::transaction(
            function () use (
                $customer,
                $data
            ) {

                $hasOpeningBills =
                    array_key_exists(
                        'opening_bills',
                        $data
                    );

                $openingBills =
                    $data['opening_bills'] ?? [];

                unset(
                    $data['opening_bills']
                );

                $customer =
                    $this->repository
                        ->update(
                            $customer,
                            $data
                        );

                if ($hasOpeningBills) {

                    $this->openingBillPosting
                        ->reverse($customer);

                    $this->syncOpeningBills(
                        $customer,
                        $openingBills
                    );

                    $this->openingBillPosting
                        ->post($customer);
                }

                return $customer->refresh();
            }
        );
    }

    public function delete(
        Customer $customer
    ): bool {
        return DB::transaction(
            fn () =>
                $this->repository
                    ->delete($customer)
        );
    }

    public function findById(
        int $id
    ): ?Customer {
        return $this->repository
            ->findById($id);
    }

    public function findByUuid(
        string $uuid
    ): ?Customer {
        return $this->repository
            ->findByUuid($uuid);
    }

    protected function createOpeningBills(
        Customer $customer,
        array $openingBills
    ): void {
        foreach (
            $openingBills as $billData
        ) {
            $billData['customer_id'] =
                $customer->id;

            $this->openingBills
                ->create($billData);
        }
    }

    protected function syncOpeningBills(
        Customer $customer,
        array $openingBills
    ): void {
        foreach (
            $openingBills as $billData
        ) {

            if (
                !empty(
                    $billData['delete']
                )
            ) {
                $this->deleteOpeningBill(
                    $customer,
                    $billData
                );

                continue;
            }

            unset(
                $billData['delete']
            );

            if (
                empty(
                    $billData['uuid']
                )
            ) {
                $billData['customer_id'] =
                    $customer->id;

                $this->openingBills
                    ->create($billData);

                continue;
            }

            $openingBill =
                $this->openingBills
                    ->findByUuid(
                        $billData['uuid']
                    );

            if (
                !$openingBill
                || $openingBill->customer_id
                    !== $customer->id
            ) {
                throw new BusinessException(
                    'Customer opening bill not found.'
                );
            }

            unset(
                $billData['uuid']
            );

            $billData['customer_id'] =
                $customer->id;

            $this->openingBills
                ->update(
                    $openingBill,
                    $billData
                );
        }
    }

    protected function deleteOpeningBill(
        Customer $customer,
        array $billData
    ): void {
        if (
            empty(
                $billData['uuid']
            )
        ) {
            throw new BusinessException(
                'Customer opening bill not found.'
            );
        }

        $openingBill =
            $this->openingBills
                ->findByUuid(
                    $billData['uuid']
                );

        if (
            !$openingBill
            || $openingBill->customer_id
                !== $customer->id
        ) {
            throw new BusinessException(
                'Customer opening bill not found.'
            );
        }

        $this->openingBills
            ->delete($openingBill);
    }
}
