<?php

namespace App\Modules\Payment\Providers;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

use App\Modules\Payment\Repositories\PaymentRepository;
use App\Modules\Payment\Repositories\PaymentAllocationRepository;

use App\Modules\Payment\Repositories\Contracts\PaymentRepositoryInterface;
use App\Modules\Payment\Repositories\Contracts\PaymentAllocationRepositoryInterface;

use App\Modules\Payment\Services\Validation\Contracts\PaymentAllocationValidatorInterface;
use App\Modules\Payment\Services\Validation\PaymentAllocationValidator;

use App\Modules\Payment\Services\Validation\Contracts\SupplierOpeningBillAllocationValidatorInterface;
use App\Modules\Payment\Services\Validation\SupplierOpeningBillAllocationValidator;

use App\Modules\Payment\Services\PaymentService;
use App\Modules\Payment\Services\PaymentAllocationService;
use App\Modules\Payment\Services\PaymentAllocationPostingService;
use App\Modules\Payment\Services\PaymentAllocationReversalService;
use App\Modules\Payment\Services\PaymentPostingService;
use App\Modules\Payment\Services\PaymentReversalService;

use App\Modules\Payment\Services\Contracts\PaymentServiceInterface;
use App\Modules\Payment\Services\Contracts\PaymentAllocationServiceInterface;
use App\Modules\Payment\Services\Contracts\PaymentAllocationPostingServiceInterface;
use App\Modules\Payment\Services\Contracts\PaymentAllocationReversalServiceInterface;
use App\Modules\Payment\Services\Contracts\PaymentPostingServiceInterface;
use App\Modules\Payment\Services\Contracts\PaymentReversalServiceInterface;

class PaymentServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(
            PaymentRepositoryInterface::class,
            PaymentRepository::class
        );

        $this->app->bind(
            PaymentAllocationRepositoryInterface::class,
            PaymentAllocationRepository::class
        );

        $this->app->bind(
            PaymentServiceInterface::class,
            PaymentService::class
        );

        $this->app->bind(
            PaymentAllocationServiceInterface::class,
            PaymentAllocationService::class
        );

        $this->app->bind(
            PaymentAllocationPostingServiceInterface::class,
            PaymentAllocationPostingService::class
        );

        $this->app->bind(
            PaymentAllocationReversalServiceInterface::class,
            PaymentAllocationReversalService::class
        );

        $this->app->bind(
            PaymentAllocationValidatorInterface::class,
            PaymentAllocationValidator::class
        );

        $this->app->bind(
            SupplierOpeningBillAllocationValidatorInterface::class,
            SupplierOpeningBillAllocationValidator::class
        );

        $this->app->bind(
            PaymentPostingServiceInterface::class,
            PaymentPostingService::class
        );

        $this->app->bind(
            PaymentReversalServiceInterface::class,
            PaymentReversalService::class
        );
    }

    public function boot(): void
    {
        Route::middleware('api')
            ->prefix('api')
            ->group(
                __DIR__ . '/../Routes/api.php'
            );
    }
}