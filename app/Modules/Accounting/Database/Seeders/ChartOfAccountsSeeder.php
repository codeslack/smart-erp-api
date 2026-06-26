<?php

namespace App\Modules\Accounting\Database\Seeders;

use Illuminate\Database\Seeder;
use App\Modules\Accounting\Models\AccountGroup;
use App\Modules\Accounting\Models\ChartOfAccount;

class ChartOfAccountsSeeder extends Seeder
{
    public function run(): void
    {
        $groups = [

            'Assets' => [
                [
                    'account_code' => '1000',
                    'account_name' => 'Cash',
                ],
                [
                    'account_code' => '1010',
                    'account_name' => 'Bank',
                ],
                [
                    'account_code' => '1100',
                    'account_name' => 'Accounts Receivable',
                ],
            ],

            'Liabilities' => [
                [
                    'account_code' => '2000',
                    'account_name' => 'Accounts Payable',
                ],
            ],

            'Equity' => [
                [
                    'account_code' => '3000',
                    'account_name' => 'Owner Equity',
                ],
            ],

            'Income' => [
                [
                    'account_code' => '4000',
                    'account_name' => 'Sales Revenue',
                ],
            ],

            'Expense' => [
                [
                    'account_code' => '5000',
                    'account_name' => 'Purchase Expense',
                ],
            ],

        ];

        foreach ($groups as $groupName => $accounts) {

            $group = AccountGroup::firstOrCreate(
                [
                    'name' => $groupName,
                ],
                [
                    'description' => $groupName . ' Group',
                    'is_active' => true,
                ]
            );

            foreach ($accounts as $account) {

                ChartOfAccount::firstOrCreate(
                    [
                        'account_code' => $account['account_code'],
                    ],
                    [
                        'account_group_id' => $group->id,
                        'account_name'     => $account['account_name'],
                        'is_system'        => true,
                        'is_active'        => true,
                    ]
                );
            }
        }
    }
}