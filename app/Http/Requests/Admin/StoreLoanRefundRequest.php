<?php

namespace App\Http\Requests\Admin;

use App\Models\LoanRepayment;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreLoanRefundRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth('admin')->user()?->can('repayments.refund') ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'loan_repayment_id' => ['required', 'integer', 'exists:loan_repayments,id'],
            'amount' => ['required', 'numeric', 'gt:0'],
            'reason' => ['required', 'string', 'min:3', 'max:2000'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $loan = $this->route('loan');
            $loanRepayment = LoanRepayment::query()->find($this->input('loan_repayment_id'));

            if (! $loan || ! $loanRepayment) {
                return;
            }

            if ((int) $loanRepayment->loan_id !== (int) $loan->id) {
                $validator->errors()->add('loan_repayment_id', 'The selected repayment does not belong to this loan.');

                return;
            }

            if (! $loanRepayment->isPayment()) {
                $validator->errors()->add('loan_repayment_id', 'Refund rows cannot be refunded.');

                return;
            }

            $refundable = $loanRepayment->refundableAmountRemaining();
            $amount = (float) $this->input('amount');

            if ($amount > $refundable + 0.0001) {
                $validator->errors()->add(
                    'amount',
                    'Refund amount cannot exceed the remaining refundable amount of ZMW '.number_format($refundable, 2).'.'
                );
            }
        });
    }
}
