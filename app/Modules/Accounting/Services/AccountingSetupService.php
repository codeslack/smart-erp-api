<?php

namespace App\Modules\Accounting\Services;

use Illuminate\Support\Facades\DB;
use App\Modules\Tenant\Models\Tenant;
use App\Modules\Accounting\Enums\AccountType;
use App\Modules\Accounting\Models\AccountGroup;
use App\Modules\Accounting\Models\ChartOfAccount;
use App\Modules\Accounting\Enums\AccountingAccounts;

class AccountingSetupService
{
    public function setup(
        Tenant $tenant
    ): void {

        if (
            ChartOfAccount::query()
                ->where('tenant_id', $tenant->id)
                ->exists()
        ) {
            return;
        }

        DB::transaction(function () use (
            $tenant
        ) {

            $groups = $this->createGroups(
                $tenant
            );

            $this->createAccounts(
                $tenant,
                $groups
            );
        });
    }

    protected function createGroups(
        Tenant $tenant
    ): array {

        $groups = [];

        foreach (
            $this->defaultGroups()
            as $group
        ) {

            $groups[$group['key']] =
                AccountGroup::create([

                    'tenant_id' =>
                        $tenant->id,

                    'name' =>
                        $group['name'],

                    'code' =>
                        $group['code'],
                ]);
        }

        return $groups;
    }

    protected function createAccounts(
        Tenant $tenant,
        array $groups
    ): void {

        $accounts = [];

        foreach (
            $this->defaultAccounts()
            as $account
        ) {

            $accounts[] = [

                'tenant_id' =>
                    $tenant->id,

                'account_group_id' =>
                    $groups[$account['group']]->id,

                'parent_id' =>
                    null,

                'account_code' =>
                    $account['code'],

                'account_name' =>
                    $account['name'],

                'account_type' =>
                    $account['type'],

                'opening_balance' =>
                    0,

                'current_balance' =>
                    0,

                'is_system' =>
                    true,

                'is_active' =>
                    true,

                'created_at' =>
                    now(),

                'updated_at' =>
                    now(),
            ];
        }

        ChartOfAccount::insert(
            $accounts
        );
    }

    protected function defaultGroups(): array
    {
        return [

            [
                'key'  => 'asset',
                'name' => 'Assets',
                'code' => 'AST',
            ],

            [
                'key'  => 'liability',
                'name' => 'Liabilities',
                'code' => 'LIA',
            ],

            [
                'key'  => 'equity',
                'name' => 'Equity',
                'code' => 'EQT',
            ],

            [
                'key'  => 'income',
                'name' => 'Income',
                'code' => 'INC',
            ],

            [
                'key'  => 'expense',
                'name' => 'Expense',
                'code' => 'EXP',
            ],
        ];
    }

    protected function defaultAccounts(): array
    {
        return [

            /*
            |--------------------------------------------------------------------------
            | Assets
            |--------------------------------------------------------------------------
            */

            [
                'group' => 'asset',
                'code'  => AccountingAccounts::CASH,
                'name'  => 'Cash',
                'type'  => AccountType::ASSET,
            ],

            [
                'group' => 'asset',
                'code'  => AccountingAccounts::BANK,
                'name'  => 'Bank Accounts',
                'type'  => AccountType::ASSET,
            ],

            [
                'group' => 'asset',
                'code'  => AccountingAccounts::ACCOUNTS_RECEIVABLE,
                'name'  => 'Accounts Receivable',
                'type'  => AccountType::ASSET,
            ],

            [
                'group' => 'asset',
                'code'  => AccountingAccounts::SUPPLIER_ADVANCES,
                'name'  => 'Supplier Advances',
                'type'  => AccountType::ASSET,
            ],

            [
                'group' => 'asset',
                'code'  => AccountingAccounts::INVENTORY,
                'name'  => 'Inventory',
                'type'  => AccountType::ASSET,
            ],

            [
                'group' => 'asset',
                'code'  => AccountingAccounts::INPUT_TAX_RECEIVABLE,
                'name'  => 'Input Tax Receivable',
                'type'  => AccountType::ASSET,
            ],

            /*
            |--------------------------------------------------------------------------
            | Liabilities
            |--------------------------------------------------------------------------
            */

            [
                'group' => 'liability',
                'code'  => AccountingAccounts::ACCOUNTS_PAYABLE,
                'name'  => 'Accounts Payable',
                'type'  => AccountType::LIABILITY,
            ],

            [
                'group' => 'liability',
                'code'  => AccountingAccounts::CUSTOMER_ADVANCES,
                'name'  => 'Customer Advances',
                'type'  => AccountType::LIABILITY,
            ],

            [
                'group' => 'liability',
                'code'  => AccountingAccounts::OUTPUT_TAX_PAYABLE,
                'name'  => 'Output Tax Payable',
                'type'  => AccountType::LIABILITY,
            ],

            /*
            |--------------------------------------------------------------------------
            | Equity
            |--------------------------------------------------------------------------
            */

            [
                'group' => 'equity',
                'code'  => AccountingAccounts::OWNER_EQUITY,
                'name'  => 'Owner Equity',
                'type'  => AccountType::EQUITY,
            ],

            /*
            |--------------------------------------------------------------------------
            | Income
            |--------------------------------------------------------------------------
            */

            [
                'group' => 'income',
                'code'  => AccountingAccounts::SALES_REVENUE,
                'name'  => 'Sales Revenue',
                'type'  => AccountType::INCOME,
            ],

            [
                'group' => 'income',
                'code'  => AccountingAccounts::SALES_RETURN,
                'name'  => 'Sales Return',
                'type'  => AccountType::INCOME,
            ],

            /*
            |--------------------------------------------------------------------------
            | Expenses
            |--------------------------------------------------------------------------
            */

            [
                'group' => 'expense',
                'code'  => AccountingAccounts::COST_OF_GOODS_SOLD,
                'name'  => 'Cost Of Goods Sold',
                'type'  => AccountType::EXPENSE,
            ],

            [
                'group' => 'expense',
                'code'  => AccountingAccounts::PURCHASE_FREIGHT,
                'name'  => 'Purchase Freight',
                'type'  => AccountType::EXPENSE,
            ],

            [
                'group' => 'expense',
                'code'  => AccountingAccounts::PURCHASE_HANDLING,
                'name'  => 'Purchase Handling Charges',
                'type'  => AccountType::EXPENSE,
            ],
        ];
    }
}