<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Support\Str;

class CompanyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user('admin')?->can('companies.update') ?? false;
    }

    protected function prepareForValidation(): void
    {
        $slugSource = $this->input('code') ?? $this->input('name');

        if ($slugSource) {
            $this->merge([
                'slug' => Str::slug($slugSource),
            ]);
        }
    }

    public function rules(): array
    {
        $companyId = $this->route('company')?->id ?? null;

        return [
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'string', 'max:255', Rule::unique('companies', 'slug')->ignore($companyId)],
            'code' => ['required', 'string', 'max:50', Rule::unique('companies', 'code')->ignore($companyId)],
            'registration_number' => ['nullable', 'string', 'max:100'],
            'tpin' => ['nullable', 'string', 'max:50'],
            'date_of_incorporation' => ['nullable', 'date'],
            'mou_expiry_date' => ['nullable', 'date', 'after_or_equal:date_of_incorporation'],
            'sector_id' => ['nullable', 'exists:sectors,id'],
            'relationship_manager_id' => ['nullable', 'exists:admins,id'],
            'loan_rate_type_id' => ['nullable', 'exists:loan_rate_types,id'],
            'maximum_loan_tenure_months' => ['nullable', 'integer', 'min:1', 'max:360'],
            'monthly_cut_off_day' => ['nullable', 'integer', 'between:1,31'],
            'pay_day' => ['nullable', 'integer', 'between:1,31'],
            'maximum_debit_ratio' => ['nullable', 'numeric', 'between:0,100'],
            'instalment_cross_over_percentage' => ['nullable', 'numeric', 'between:0,100'],
            'arrangement_fee_percentage' => ['nullable', 'numeric', 'between:0,100'],
            'contact_email' => ['nullable', 'email', 'max:255'],
            'contact_phone' => ['nullable', 'string', 'max:50'],
            'address_line1' => ['nullable', 'string', 'max:255'],
            'address_line2' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:120'],
            'state' => ['nullable', 'string', 'max:120'],
            'postal_code' => ['nullable', 'string', 'max:20'],
            'country' => ['nullable', 'string', 'max:120'],
            'status' => ['required', 'in:pending,active,suspended'],
        ];
    }
}
