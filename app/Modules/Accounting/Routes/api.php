<?php

use Illuminate\Support\Facades\Route;

use App\Modules\Accounting\Controllers\DayBookController;
use App\Modules\Accounting\Controllers\CashBookController;
use App\Modules\Accounting\Controllers\CashFlowController;
use App\Modules\Accounting\Controllers\ProfitLossController;
use App\Modules\Accounting\Controllers\BalanceSheetController;
use App\Modules\Accounting\Controllers\JournalEntryController;
use App\Modules\Accounting\Controllers\TrialBalanceController;
use App\Modules\Accounting\Controllers\AccountGroupController;
use App\Modules\Accounting\Controllers\GeneralLedgerController;
use App\Modules\Accounting\Controllers\CustomerAgingController;
use App\Modules\Accounting\Controllers\SupplierAgingController;
use App\Modules\Accounting\Controllers\ChartOfAccountController;
use App\Modules\Accounting\Controllers\PayableSummaryController;
use App\Modules\Accounting\Controllers\CustomerStatementController;
use App\Modules\Accounting\Controllers\SupplierStatementController;
use App\Modules\Accounting\Controllers\ReceivableSummaryController;

Route::middleware([
    'auth:sanctum',
    'tenant',
])->group(function () {

    Route::prefix('accounting')
        ->group(function () {

            Route::get(
                'cash-book',
                [CashBookController::class, 'index']
            )->name(
                'cash-book.index'
            );

            Route::get(
                'day-book',
                [DayBookController::class, 'index']
            )->name(
                'day-book.index'
            );

            Route::get(
                'payable-summary',
                [PayableSummaryController::class, 'index']
            )->name(
                'payable-summary.index'
            );

            Route::get(
                'receivable-summary',
                [ReceivableSummaryController::class, 'index']
            )->name(
                'receivable-summary.index'
            );

            Route::get(
                'cash-flow',
                [CashFlowController::class, 'index']
            )->name(
                'cash-flow.index'
            );

            Route::get(
                'supplier-aging',
                [SupplierAgingController::class, 'index']
            )->name(
                'supplier-aging.index'
            );

            Route::get(
                'customer-aging',
                [CustomerAgingController::class, 'index']
            )->name(
                'customer-aging.index'
            );

            Route::get(
                'supplier-statements/{supplierId}',
                [SupplierStatementController::class, 'show']
            )->name(
                'supplier-statements.show'
            );

            Route::get(
                'customer-statements/{customerId}',
                [CustomerStatementController::class, 'show']
            )->name(
                'customer-statements.show'
            );

            Route::get(
                'balance-sheet',
                [BalanceSheetController::class, 'index']
            )->name(
                'balance-sheet.index'
            );

            Route::get(
                'profit-loss',
                [ProfitLossController::class, 'index']
            )->name(
                'profit-loss.index'
            );

            Route::get(
                'trial-balance',
                [TrialBalanceController::class, 'index']
            )->name(
                'trial-balance.index'
            );

            Route::get(
                'general-ledger',
                [GeneralLedgerController::class, 'index']
            )->name(
                'general-ledger.index'
            );

            Route::post(
                'journal-entries/{journalEntry}/post',
                [JournalEntryController::class, 'post']
            )->name('journal-entries.post');

            Route::post(
                'journal-entries/{journalEntry}/cancel',
                [JournalEntryController::class, 'cancel']
            )->name('journal-entries.cancel');

            Route::apiResource(
                'journal-entries',
                JournalEntryController::class
            );

            Route::apiResource(
                'account-groups',
                AccountGroupController::class
            );

            Route::apiResource(
                'chart-of-accounts',
                ChartOfAccountController::class
            );
        });
});
