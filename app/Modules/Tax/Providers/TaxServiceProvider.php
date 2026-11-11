<?php

namespace App\Modules\Tax\Providers;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

use App\Modules\Tax\Repositories\TaxRepository;
use App\Modules\Tax\Repositories\TaxGroupRepository;
use App\Modules\Tax\Repositories\TaxComponentRepository;
use App\Modules\Tax\Repositories\TaxRegistrationRepository;
use App\Modules\Tax\Repositories\TaxAccountingMappingRepository;
use App\Modules\Tax\Repositories\TaxApplicabilityRuleRepository;
use App\Modules\Tax\Repositories\TaxGroupComponentRepository;
use App\Modules\Tax\Repositories\Contracts\TaxRepositoryInterface;
use App\Modules\Tax\Repositories\Contracts\TaxGroupRepositoryInterface;
use App\Modules\Tax\Repositories\Contracts\TaxComponentRepositoryInterface;
use App\Modules\Tax\Repositories\Contracts\TaxRegistrationRepositoryInterface;
use App\Modules\Tax\Repositories\Contracts\TaxAccountingMappingRepositoryInterface;
use App\Modules\Tax\Repositories\Contracts\TaxApplicabilityRuleRepositoryInterface;
use App\Modules\Tax\Repositories\Contracts\TaxGroupComponentRepositoryInterface;

class TaxServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(
            TaxRepositoryInterface::class,
            TaxRepository::class
        );

        $this->app->bind(
            TaxComponentRepositoryInterface::class,
            TaxComponentRepository::class
        );

        $this->app->bind(
            TaxGroupRepositoryInterface::class,
            TaxGroupRepository::class
        );

        $this->app->bind(
            TaxGroupComponentRepositoryInterface::class,
            TaxGroupComponentRepository::class
        );

        $this->app->bind(
            TaxApplicabilityRuleRepositoryInterface::class,
            TaxApplicabilityRuleRepository::class
        );

        $this->app->bind(
            TaxAccountingMappingRepositoryInterface::class,
            TaxAccountingMappingRepository::class
        );

        $this->app->bind(
            TaxRegistrationRepositoryInterface::class,
            TaxRegistrationRepository::class
        );
    }

    public function boot(): void
    {
        Route::middleware('api')
            ->prefix('api')
            ->group(
                __DIR__ . '/../routes/api.php'
            );
    }
}