<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user('admin')?->can('admins.update') ?? false;
    }

    public function rules(): array
    {
        $adminId = $this->route('user')?->id ?? null;
        $passwordRule = ['nullable', 'string', 'min:8'];
        $companyId = $this->input('company_id');

        return [
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:255', Rule::unique('admins', 'email')->ignore($adminId)],
            'company_id' => ['required', 'exists:companies,id'],
            'branch_id' => ['required', 'exists:branches,id'],
            'employee_number' => [
                'nullable',
                'string',
                'max:100',
                function ($attribute, $value, $fail) use ($companyId, $adminId) {
                    if ($value && $companyId) {
                        $exists = \App\Models\Admin::where('company_id', $companyId)
                            ->where('employee_number', $value)
                            ->when($adminId, fn($query) => $query->where('id', '!=', $adminId))
                            ->exists();
                        if ($exists) {
                            $fail('The employee number has already been taken for this company.');
                        }
                    }
                },
            ],
            'nrc' => [
                'nullable',
                'string',
                'max:100',
                function ($attribute, $value, $fail) use ($companyId, $adminId) {
                    if ($value && $companyId) {
                        $exists = \App\Models\Admin::where('company_id', $companyId)
                            ->where('nrc', $value)
                            ->when($adminId, fn($query) => $query->where('id', '!=', $adminId))
                            ->exists();
                        if ($exists) {
                            $fail('The NRC number has already been taken for this company.');
                        }
                    }
                },
            ],
            'phone' => ['nullable', 'string', 'max:50'],
            'is_active' => ['nullable', 'boolean'],
            'is_relationship_manager' => ['nullable', 'boolean'],
            'role_names' => ['nullable', 'array'],
            'role_names.*' => ['string', 'exists:roles,name'],
            'password' => $passwordRule,
        ];
    }
}
