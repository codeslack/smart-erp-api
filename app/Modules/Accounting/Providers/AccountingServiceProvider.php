<?php

namespace App\Modules\Accounting\Providers;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

use App\Modules\Accounting\Repositories\DayBookRepository;
use App\Modules\Accounting\Repositories\CashBookRepository;
use App\Modules\Accounting\Repositories\CashFlowRepository;
use App\Modules\Accounting\Services\AccountingPostingService;
use App\Modules\Accounting\Repositories\ProfitLossRepository;
use App\Modules\Accounting\Repositories\BalanceSheetRepository;
use App\Modules\Accounting\Repositories\AccountGroupRepository;
use App\Modules\Accounting\Repositories\JournalEntryRepository;
use App\Modules\Accounting\Repositories\TrialBalanceRepository;
use App\Modules\Accounting\Repositories\GeneralLedgerRepository;
use App\Modules\Accounting\Repositories\AccountLedgerRepository;
use App\Modules\Accounting\Repositories\CustomerAgingRepository;
use App\Modules\Accounting\Repositories\SupplierAgingRepository;
use App\Modules\Accounting\Repositories\PayableSummaryRepository;
use App\Modules\Accounting\Repositories\ChartOfAccountRepository;
use App\Modules\Accounting\Repositories\ReceivableSummaryRepository;
use App\Modules\Accounting\Repositories\CustomerStatementRepository;
use App\Modules\Accounting\Repositories\SupplierStatementRepository;

use App\Modules\Accounting\Repositories\Contracts\DayBookRepositoryInterface;
use App\Modules\Accounting\Repositories\Contracts\CashFlowRepositoryInterface;
use App\Modules\Accounting\Repositories\Contracts\CashBookRepositoryInterface;
use App\Modules\Accounting\Services\Contracts\AccountingPostingServiceInterface;
use App\Modules\Accounting\Repositories\Contracts\ProfitLossRepositoryInterface;
use App\Modules\Accounting\Repositories\Contracts\BalanceSheetRepositoryInterface;
use App\Modules\Accounting\Repositories\Contracts\AccountGroupRepositoryInterface;
use App\Modules\Accounting\Repositories\Contracts\JournalEntryRepositoryInterface;
use App\Modules\Accounting\Repositories\Contracts\TrialBalanceRepositoryInterface;
use App\Modules\Accounting\Repositories\Contracts\GeneralLedgerRepositoryInterface;
use App\Modules\Accounting\Repositories\Contracts\AccountLedgerRepositoryInterface;
use App\Modules\Accounting\Repositories\Contracts\SupplierAgingRepositoryInterface;
use App\Modules\Accounting\Repositories\Contracts\CustomerAgingRepositoryInterface;
use App\Modules\Accounting\Repositories\Contracts\ChartOfAccountRepositoryInterface;
use App\Modules\Accounting\Repositories\Contracts\PayableSummaryRepositoryInterface;
use App\Modules\Accounting\Repositories\Contracts\ReceivableSummaryRepositoryInterface;
use App\Modules\Accounting\Repositories\Contracts\CustomerStatementRepositoryInterface;
use App\Modules\Accounting\Repositories\Contracts\SupplierStatementRepositoryInterface;


class AccountingServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(
            AccountingPostingServiceInterface::class,
            AccountingPostingService::class
        );

        $this->app->bind(
            AccountGroupRepositoryInterface::class,
            AccountGroupRepository::class
        );

        $this->app->bind(
            ChartOfAccountRepositoryInterface::class,
            ChartOfAccountRepository::class
        );

        $this->app->bind(
            JournalEntryRepositoryInterface::class,
            JournalEntryRepository::class
        );

        $this->app->bind(
            AccountLedgerRepositoryInterface::class,
            AccountLedgerRepository::class
        );

        $this->app->bind(
            GeneralLedgerRepositoryInterface::class,
            GeneralLedgerRepository::class
        );

        $this->app->bind(
            TrialBalanceRepositoryInterface::class,
            TrialBalanceRepository::class
        );

        $this->app->bind(
            ProfitLossRepositoryInterface::class,
            ProfitLossRepository::class
        );

        $this->app->bind(
            BalanceSheetRepositoryInterface::class,
            BalanceSheetRepository::class
        );

        $this->app->bind(
            CustomerStatementRepositoryInterface::class,
            CustomerStatementRepository::class
        );

        $this->app->bind(
            SupplierStatementRepositoryInterface::class,
            SupplierStatementRepository::class
        );

        $this->app->bind(
            CustomerAgingRepositoryInterface::class,
            CustomerAgingRepository::class
        );

        $this->app->bind(
            SupplierAgingRepositoryInterface::class,
            SupplierAgingRepository::class
        );

        $this->app->bind(
            CashFlowRepositoryInterface::class,
            CashFlowRepository::class
        );

        $this->app->bind(
            ReceivableSummaryRepositoryInterface::class,
            ReceivableSummaryRepository::class
        );

        $this->app->bind(
            PayableSummaryRepositoryInterface::class,
            PayableSummaryRepository::class
        );

        $this->app->bind(
            DayBookRepositoryInterface::class,
            DayBookRepository::class
        );

        $this->app->bind(
            CashBookRepositoryInterface::class,
            CashBookRepository::class
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
