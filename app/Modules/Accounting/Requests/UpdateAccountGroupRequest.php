<?php

namespace App\Modules\Accounting\Requests;

use Illuminate\Foundation\Http\FormRequest;
use App\Core\Validation\TenantRule;
use App\Modules\Accounting\Models\AccountGroup;

class UpdateAccountGroupRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $group = $this->route('accountGroup') ?? $this->route('account_group');
        $id = $group instanceof AccountGroup
            ? $group->id
            : (is_numeric($group) ? $group : AccountGroup::where('uuid', $group)->value('id'));

        return [
            'name' => [
                'required',
                'string',
                'max:255',
                TenantRule::unique(
                    'account_groups',
                    'name'
                )->ignore($id),
            ],

            'code' => [
                'nullable',
                'string',
                'max:50',
            ],
        ];
    }
}
