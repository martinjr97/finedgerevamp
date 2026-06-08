<?php

namespace App\Http\Resources\Api\V1;

use App\Models\Channel;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RepaymentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'repayment_number' => $this->repayment_number,
            'total_amount' => $this->total_amount,
            'phone_number' => $this->phone_number,
            'external_reference' => $this->external_reference,
            'external_transaction_id' => $this->external_transaction_id,
            'status' => $this->status,
            'status_message' => $this->status_message,
            'customer' => $this->whenLoaded('customer', function () {
                return [
                    'id' => $this->customer->id,
                    'name' => $this->customer->full_name,
                    'email' => $this->customer->email,
                    'phone' => $this->customer->phone,
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
            'loan_repayments' => $this->whenLoaded('loanRepayments', function () {
                return $this->loanRepayments->map(function ($loanRepayment) {
                    return [
                        'loan_id' => $loanRepayment->loan_id,
                        'loan_number' => $loanRepayment->loan->loan_number ?? null,
                        'amount' => $loanRepayment->amount,
                        'principal_amount' => $loanRepayment->principal_amount,
                        'interest_amount' => $loanRepayment->interest_amount,
                        'processing_fee_amount' => $loanRepayment->processing_fee_amount,
                    ];
                });
            }),
            'processed_at' => $this->processed_at?->toIso8601String(),
            'created_at' => $this->created_at->toIso8601String(),
            'updated_at' => $this->updated_at->toIso8601String(),
        ];
    }
}

