<?php

// app/Modules/Supplier/Services/SupplierService.php

namespace App\Modules\Supplier\Services;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

use App\Core\Exceptions\BusinessException;
use App\Core\Services\BaseService;

use App\Modules\Supplier\Models\Supplier;
use App\Modules\Supplier\Repositories\Contracts\SupplierRepositoryInterface;

use App\Modules\SupplierOpeningBill\Services\SupplierOpeningBillService;
use App\Modules\SupplierOpeningBill\Services\SupplierOpeningBillPostingService;

class SupplierService extends BaseService
{
    public function __construct(
        protected SupplierRepositoryInterface $repository,
        protected SupplierOpeningBillService $openingBills,
        protected SupplierOpeningBillPostingService $openingBillPosting,
    ) {}

    public function paginate(int $perPage = 15): LengthAwarePaginator
    {
        return $this->repository->paginate($perPage);
    }

    public function create(array $data): Supplier
    {
        $supplier = DB::transaction(function () use ($data) {
            $openingBills = $data['opening_bills'] ?? null;
            unset($data['opening_bills']);

            $supplier = $this->repository->create($data);

            if ($openingBills !== null) {
                $this->syncOpeningBills($supplier, $openingBills);
                $this->openingBillPosting->post($supplier);
            }

            return $supplier;
        });

        return $this->repository->findByUuid($supplier->uuid);
    }

    public function update(
        Supplier $supplier,
        array $data
    ): Supplier {
        $supplier = DB::transaction(function () use ($supplier, $data) {
            $hasOpeningBills = array_key_exists('opening_bills', $data);
            $openingBills = $data['opening_bills'] ?? null;

            unset($data['opening_bills']);

            $supplier = $this->repository->update(
                $supplier,
                $data
            );

            if ($hasOpeningBills) {
                $this->openingBillPosting->reverse($supplier);

                $this->syncOpeningBills(
                    $supplier,
                    $openingBills ?? []
                );

                $this->openingBillPosting->post($supplier);
            }

            return $supplier;
        });

        return $this->repository->findByUuid($supplier->uuid);
    }

    public function delete(Supplier $supplier): bool
    {
        return DB::transaction(function () use ($supplier) {
            $this->openingBillPosting->reverse($supplier);

            return $this->repository->delete($supplier);
        });
    }

    public function findById(int $id): ?Supplier
    {
        return $this->repository->findById($id);
    }

    public function findByUuid(string $uuid): ?Supplier
    {
        return $this->repository->findByUuid($uuid);
    }

    protected function syncOpeningBills(
        Supplier $supplier,
        array $openingBills
    ): void {
        foreach ($openingBills as $billData) {
            $delete = (bool) ($billData['delete'] ?? false);
            $uuid = $billData['uuid'] ?? null;

            if ($uuid) {
                $openingBill = $this->openingBills->findByUuid($uuid);

                if (!$openingBill) {
                    throw new BusinessException(
                        'Supplier opening bill not found.'
                    );
                }

                if ((int) $openingBill->supplier_id !== (int) $supplier->id) {
                    throw new BusinessException(
                        'Supplier opening bill does not belong to this supplier.'
                    );
                }

                if ($delete) {
                    $this->openingBills->delete($openingBill);
                    continue;
                }

                unset(
                    $billData['uuid'],
                    $billData['delete']
                );

                $billData['supplier_id'] = $supplier->id;

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

            $billData['supplier_id'] = $supplier->id;

            $this->openingBills->create($billData);
        }
    }
}