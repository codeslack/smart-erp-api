<?php

namespace App\Modules\Accounting\Services;

use Illuminate\Support\Facades\DB;
use App\Modules\Tenant\Models\Tenant;
use App\Modules\Accounting\Models\AccountGroup;
use App\Modules\Accounting\Models\ChartOfAccount;

class AccountingSetupService
{
    public function setup(Tenant $tenant): void
    {
        if (
            ChartOfAccount::where(
                'tenant_id',
                $tenant->id
            )->exists()
        ) {
            return;
        }

        DB::transaction(function () use ($tenant) {

            $groups = [];

            foreach ($this->defaultGroups() as $group) {

                $groups[$group['key']] =
                    AccountGroup::create([
                        'tenant_id' => $tenant->id,
                        'name'      => $group['name'],
                        'code'      => $group['code'],
                    ]);
            }

            foreach ($this->defaultAccounts() as $account) {

                ChartOfAccount::create([
                    'tenant_id'        => $tenant->id,
                    'account_group_id' => $groups[$account['group']]->id,

                    'parent_id'        => null,

                    'account_code'     => $account['code'],
                    'account_name'     => $account['name'],
                    'account_type'     => $account['type'],

                    'opening_balance' => 0,
                    'current_balance' => 0,

                    'is_system' => true,
                    'is_active' => true,
                ]);
            }
        });
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

            // Assets

            [
                'group' => 'asset',
                'code'  => '1000',
                'name'  => 'Cash',
                'type'  => 'asset',
            ],

            [
                'group' => 'asset',
                'code'  => '1010',
                'name'  => 'Bank',
                'type'  => 'asset',
            ],

            [
                'group' => 'asset',
                'code'  => '1100',
                'name'  => 'Accounts Receivable',
                'type'  => 'asset',
            ],

            [
                'group' => 'asset',
                'code'  => '1200',
                'name'  => 'Inventory',
                'type'  => 'asset',
            ],

            // Liabilities

            [
                'group' => 'liability',
                'code'  => '2000',
                'name'  => 'Accounts Payable',
                'type'  => 'liability',
            ],

            // Equity

            [
                'group' => 'equity',
                'code'  => '3000',
                'name'  => 'Owner Equity',
                'type'  => 'equity',
            ],

            // Income

            [
                'group' => 'income',
                'code'  => '4000',
                'name'  => 'Sales Revenue',
                'type'  => 'income',
            ],

            [
                'group' => 'income',
                'code'  => '4100',
                'name'  => 'Sales Return',
                'type'  => 'income',
            ],

            // Expense

            [
                'group' => 'expense',
                'code'  => '5000',
                'name'  => 'Cost of Goods Sold',
                'type'  => 'expense',
            ],

            [
                'group' => 'expense',
                'code'  => '5100',
                'name'  => 'Purchase Return',
                'type'  => 'expense',
            ],
        ];
    }
}
