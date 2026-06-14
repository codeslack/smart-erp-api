<?php

namespace App\Modules\Customer\Repositories;

use App\Core\Repositories\BaseRepository;
use App\Modules\Customer\Models\Customer;
use App\Modules\Customer\Repositories\Contracts\CustomerRepositoryInterface;

class CustomerRepository
    extends BaseRepository
    implements CustomerRepositoryInterface
{
    public function __construct(
        Customer $model
    ) {
        parent::__construct($model);
    }
}
