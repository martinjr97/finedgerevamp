<?php

namespace App\Http\Resources\Api\V1;

use App\Models\Channel;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LoanResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'loan_number' => $this->loan_number,
            'principal_amount' => $this->principal_amount,
            'processing_fee' => $this->processing_fee,
            'interest_accrued' => $this->interest_accrued,
            'total_amount' => $this->total_amount,
            'amount_paid' => $this->amount_paid,
            'outstanding_balance' => $this->outstanding_balance,
            'tenure_months' => $this->tenure_months,
            'loan_start_date' => $this->loan_start_date?->format('Y-m-d'),
            'loan_end_date' => $this->loan_end_date?->format('Y-m-d'),
            'first_payment_date' => $this->first_payment_date?->format('Y-m-d'),
            'last_payment_date' => $this->last_payment_date?->format('Y-m-d'),
            'status' => $this->status,
            'accrual_type' => $this->accrual_type,
            'customer' => $this->whenLoaded('customer', function () {
                return [
                    'id' => $this->customer->id,
                    'name' => $this->customer->full_name,
                    'email' => $this->customer->email,
                    'phone' => $this->customer->phone,
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
                ];
            }),
            'channel' => $this->whenLoaded('channel', function () {
                $type = $this->channel->type ?? Channel::TYPE_MOBILE_WALLET;

                return [
                    'id' => $this->channel->id,
                    'name' => $this->channel->name,
                    'type' => $type,
                    'type_label' => match ($type) {
                        Channel::TYPE_BANK => 'Bank Transfer',
                        Channel::TYPE_CASH => 'Cash',
                        default => 'Mobile Money',
                    },
                ];
            }),
            'disbursement_destination' => [
                'channel_type' => $this->disbursementChannelType(),
                'channel_type_label' => $this->disbursementChannelTypeLabel(),
                'destination_summary' => $this->disbursementDestinationSummary(),
                'destination_label' => $this->disbursementDestinationLabel(),
            ],
            'approver' => $this->whenLoaded('approver', function () {
                return [
                    'id' => $this->approver->id,
                    'name' => $this->approver->full_name,
                ];
            }),
            'approval_notes' => $this->approval_notes,
            'approved_at' => $this->approved_at?->toIso8601String(),
            'created_at' => $this->created_at->toIso8601String(),
            'updated_at' => $this->updated_at->toIso8601String(),
        ];
    }
}

