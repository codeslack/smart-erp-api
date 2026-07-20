<?php

namespace App\Modules\AdvanceAllocation\Providers;

use Illuminate\Support\ServiceProvider;

use App\Modules\AdvanceAllocation\Repositories\AdvanceAllocationRepository;

use App\Modules\AdvanceAllocation\Repositories\Contracts\AdvanceAllocationRepositoryInterface;

class AdvanceAllocationServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(

            AdvanceAllocationRepositoryInterface::class,

            AdvanceAllocationRepository::class
        );
    }
}