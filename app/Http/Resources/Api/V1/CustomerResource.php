<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CustomerResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'first_name' => $this->first_name,
            'last_name' => $this->last_name,
            'full_name' => $this->full_name,
            'email' => $this->email,
            'phone' => $this->phone,
            'date_of_birth' => $this->date_of_birth?->format('Y-m-d'),
            'gender' => $this->gender,
            'national_id' => $this->national_id,
            'status' => $this->status,
            'kyc_status' => $this->kyc_status,
            'employment_status' => $this->employment_status,
            'annual_income' => $this->annual_income,
            'avatar_path' => $this->avatar_path,
            'preferred_language' => $this->preferred_language,
            'address' => [
                'line1' => $this->address_line1,
                'line2' => $this->address_line2,
                'city' => $this->city,
                'state' => $this->state,
                'postal_code' => $this->postal_code,
                'country' => $this->country,
            ],
            'company' => $this->whenLoaded('company', function () {
                return [
                    'id' => $this->company->id,
                    'name' => $this->company->name,
                ];
            }),
            'loan_product' => $this->whenLoaded('loanProduct', function () {
                return [
                    'id' => $this->loanProduct->id,
                    'name' => $this->loanProduct->name,
                    'code' => $this->loanProduct->code,
                ];
            }),
            'customer_group' => $this->whenLoaded('customerGroup', function () {
                return [
                    'id' => $this->customerGroup->id,
                    'name' => $this->customerGroup->name,
                    'code' => $this->customerGroup->code,
                ];
            }),
            'must_change_pin' => $this->must_change_pin,
            'employment_status' => $this->employment_status,
            'annual_income' => $this->annual_income,
            'gross_salary' => $this->gross_salary,
            'net_salary' => $this->net_salary,
            'maximum_loan_take' => $this->maximum_loan_take,
            'last_login_at' => $this->last_login_at?->toIso8601String(),
            'created_at' => $this->created_at->toIso8601String(),
            'updated_at' => $this->updated_at->toIso8601String(),
        ];
    }
}

