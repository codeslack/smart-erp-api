<?php

// app/Modules/Customer/Services/CustomerService.php

namespace App\Modules\Customer\Services;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

use App\Core\Exceptions\BusinessException;
use App\Core\Services\BaseService;

use App\Modules\Customer\Models\Customer;
use App\Modules\Customer\Repositories\Contracts\CustomerRepositoryInterface;

use App\Modules\CustomerOpeningBill\Services\CustomerOpeningBillService;
use App\Modules\CustomerOpeningBill\Services\CustomerOpeningBillPostingService;

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
        return $this->repository->paginate($perPage);
    }

    public function create(
        array $data
    ): Customer {
        $customer = DB::transaction(function () use ($data) {
            $openingBills = $data['opening_bills'] ?? null;

            unset($data['opening_bills']);

            $customer = $this->repository->create($data);

            if ($openingBills !== null) {
                $this->syncOpeningBills(
                    $customer,
                    $openingBills
                );

                $this->openingBillPosting->post($customer);
            }

            return $customer;
        });

        return $this->repository->findByUuid(
            $customer->uuid
        );
    }

    public function update(
        Customer $customer,
        array $data
    ): Customer {
        $customer = DB::transaction(function () use (
            $customer,
            $data
        ) {
            $hasOpeningBills = array_key_exists(
                'opening_bills',
                $data
            );

            $openingBills = $data['opening_bills'] ?? null;

            unset($data['opening_bills']);

            $customer = $this->repository->update(
                $customer,
                $data
            );

            if ($hasOpeningBills) {
                $this->openingBillPosting->reverse(
                    $customer
                );

                $this->syncOpeningBills(
                    $customer,
                    $openingBills ?? []
                );

                $this->openingBillPosting->post(
                    $customer
                );
            }

            return $customer;
        });

        return $this->repository->findByUuid(
            $customer->uuid
        );
    }

    public function delete(
        Customer $customer
    ): bool {
        return DB::transaction(function () use ($customer) {
            $this->openingBillPosting->reverse($customer);

            return $this->repository->delete($customer);
        });
    }

    public function findById(
        int $id
    ): ?Customer {
        return $this->repository->findById($id);
    }

    public function findByUuid(
        string $uuid
    ): ?Customer {
        return $this->repository->findByUuid($uuid);
    }

    protected function syncOpeningBills(
        Customer $customer,
        array $openingBills
    ): void {
        foreach ($openingBills as $billData) {
            $delete = (bool) ($billData['delete'] ?? false);
            $uuid = $billData['uuid'] ?? null;

            if ($uuid) {
                $openingBill = $this->openingBills->findByUuid(
                    $uuid
                );

                if (!$openingBill) {
                    throw new BusinessException(
                        'Customer opening bill not found.'
                    );
                }

                if (
                    (int) $openingBill->customer_id
                    !== (int) $customer->id
                ) {
                    throw new BusinessException(
                        'Customer opening bill does not belong to this customer.'
                    );
                }

                if ($delete) {
                    $this->openingBills->delete(
                        $openingBill
                    );

                    continue;
                }

                unset(
                    $billData['uuid'],
                    $billData['delete']
                );

                $billData['customer_id'] = $customer->id;

                $this->openingBills->update(
                    $openingBill,
                    $billData
                );

                continue;
            }

            if ($delete) {
                continue;
            }

            unset($billData['delete']);

            $billData['customer_id'] = $customer->id;

            $this->openingBills->create(
                $billData
            );
        }
    }
}