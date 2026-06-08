<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\Loan;
use App\Models\Repayment;
use App\Support\CommunicationLogger;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class CustomerNotificationService
{
    public function sendRepaymentCompleted(Repayment $repayment, string $completionSource = 'manual_approval'): void
    {
        $repayment->loadMissing(['customer', 'channel', 'loanRepayments.loan']);
        $customer = $repayment->customer;

        if (! $customer) {
            return;
        }

        $totalOutstanding = (float) $customer->getTotalOutstandingBalance();
        $loanBreakdown = $repayment->loanRepayments
            ->map(function ($item) {
                $loanNumber = $item->loan?->loan_number ?? 'Loan';

                return sprintf(
                    '- %s: ZMW %s (principal %s, interest %s, fee %s)',
                    $loanNumber,
                    number_format((float) $item->amount, 2),
                    number_format((float) $item->principal_amount, 2),
                    number_format((float) $item->interest_amount, 2),
                    number_format((float) $item->processing_fee_amount, 2)
                );
            })
            ->implode("\n");

        if ($loanBreakdown === '') {
            $loanBreakdown = '- Allocation details will appear once processing entries are completed.';
        }

        $subject = 'Repayment Confirmed - '.$repayment->repayment_number;
        $statusText = $completionSource === 'provider_confirmation'
            ? 'confirmed by the payment provider'
            : 'approved and processed';

        $emailMessage = implode("\n", [
            'Dear '.$customer->first_name.',',
            '',
            'Your repayment has been '.$statusText.'.',
            '',
            'Repayment number: '.$repayment->repayment_number,
            'Amount paid: ZMW '.number_format((float) $repayment->total_amount, 2),
            'Channel: '.($repayment->channel?->name ?? 'N/A'),
            'Processed at: '.(($repayment->processed_at ?? now())->format('d M Y, H:i')),
            '',
            'Allocation summary:',
            $loanBreakdown,
            '',
            'Total outstanding balance: ZMW '.number_format($totalOutstanding, 2),
            '',
            'You can view full details on your dashboard statement and notifications.',
            '',
            config('app.name').' Team',
        ]);

        $smsMessage = sprintf(
            'Repayment %s of ZMW %s confirmed. Check dashboard for updated balances.',
            $repayment->repayment_number,
            number_format((float) $repayment->total_amount, 2)
        );

        $metadata = [
            'notification_type' => 'repayment_completed',
            'repayment_id' => $repayment->id,
            'repayment_number' => $repayment->repayment_number,
            'completion_source' => $completionSource,
            'channel' => $repayment->channel?->name,
        ];

        $this->sendEmail($customer, $subject, $emailMessage, $metadata);
        $this->sendSms($customer, $smsMessage, $metadata);
    }

    public function sendLoanDisbursed(Loan $loan): void
    {
        $loan->loadMissing(['customer', 'loanProduct', 'channel']);
        $customer = $loan->customer;

        if (! $customer) {
            return;
        }

        $subject = 'Loan Disbursed - '.$loan->loan_number;
        $disbursedAt = $loan->disbursed_at ? $loan->disbursed_at->format('d M Y, H:i') : now()->format('d M Y, H:i');
        $emailMessage = implode("\n", [
            'Dear '.$customer->first_name.',',
            '',
            'Your loan has been disbursed successfully.',
            '',
            'Loan number: '.$loan->loan_number,
            'Product: '.($loan->loanProduct?->name ?? 'N/A'),
            'Principal amount: ZMW '.number_format((float) $loan->principal_amount, 2),
            'Processing fee: ZMW '.number_format((float) $loan->processing_fee, 2),
            'Total loan amount: ZMW '.number_format((float) $loan->total_amount, 2),
            'Disbursed at: '.$disbursedAt,
            'Disbursement channel: '.($loan->channel?->name ?? 'N/A'),
            'Reference: '.($loan->disbursement_reference ?? 'N/A'),
            '',
            'Next payment date: '.($loan->first_payment_date?->format('d M Y') ?? 'Check schedule on dashboard'),
            'Loan end date: '.($loan->loan_end_date?->format('d M Y') ?? 'N/A'),
            '',
            'Please keep this information for your records.',
            '',
            config('app.name').' Team',
        ]);

        $smsMessage = sprintf(
            'Loan %s disbursed: ZMW %s. Ref %s.',
            $loan->loan_number,
            number_format((float) $loan->principal_amount, 2),
            $loan->disbursement_reference ?? 'N/A'
        );

        $metadata = [
            'notification_type' => 'loan_disbursed',
            'loan_id' => $loan->id,
            'loan_number' => $loan->loan_number,
            'principal_amount' => (float) $loan->principal_amount,
        ];

        $this->sendEmail($customer, $subject, $emailMessage, $metadata);
        $this->sendSms($customer, $smsMessage, $metadata);
    }

    /**
     * @param  array<string, mixed>  $change
     */
    public function sendLoanPaymentDetailsChanged(Loan $loan, array $change): void
    {
        $loan->loadMissing(['customer', 'loanProduct', 'channel']);
        $customer = $loan->customer;

        if (! $customer) {
            return;
        }

        $stage = (string) data_get($change, 'stage', 'processing');
        $stageText = match ($stage) {
            'approval' => 'during loan approval',
            'disbursement' => 'before loan disbursement',
            default => 'during loan processing',
        };

        $oldChannel = data_get($change, 'old.channel_name') ?? 'N/A';
        $newChannel = data_get($change, 'new.channel_name') ?? ($loan->channel?->name ?? 'N/A');
        $oldAccountNumber = data_get($change, 'old.account_number') ?? 'N/A';
        $newAccountNumber = data_get($change, 'new.account_number') ?? ($loan->disbursement_phone_number ?? 'N/A');
        $reason = (string) data_get($change, 'reason', 'Operational update');

        $subject = 'Payment Details Updated - '.$loan->loan_number;
        $emailMessage = implode("\n", [
            'Dear '.$customer->first_name.',',
            '',
            'Your payment details for the loan below have been updated '.$stageText.'.',
            '',
            'Loan number: '.$loan->loan_number,
            'Product: '.($loan->loanProduct?->name ?? 'N/A'),
            'Previous channel: '.$oldChannel,
            'New channel: '.$newChannel,
            'Previous account number: '.$oldAccountNumber,
            'New account number: '.$newAccountNumber,
            'Reason: '.$reason,
            '',
            'If you did not request this change, please contact support immediately.',
            '',
            config('app.name').' Team',
        ]);

        $smsMessage = sprintf(
            'Loan %s payment details updated. Channel: %s, Account: %s.',
            $loan->loan_number,
            $newChannel,
            $newAccountNumber
        );

        $metadata = [
            'notification_type' => 'loan_payment_details_changed',
            'loan_id' => $loan->id,
            'loan_number' => $loan->loan_number,
            'stage' => $stage,
            'reason' => $reason,
            'old' => data_get($change, 'old'),
            'new' => data_get($change, 'new'),
        ];

        $this->sendEmail($customer, $subject, $emailMessage, $metadata);
        $this->sendSms($customer, $smsMessage, $metadata);
    }

    private function sendEmail(Customer $customer, string $subject, string $message, array $metadata = []): void
    {
        if (! $customer->email) {
            return;
        }

        try {
            Mail::raw($message, function ($mail) use ($customer, $subject) {
                $mail->to($customer->email, $customer->full_name)
                    ->subject($subject);
            });

            CommunicationLogger::log(
                subject: $subject,
                message: $message,
                type: 'email',
                recipient: $customer,
                metadata: $metadata
            );
        } catch (\Throwable $e) {
            Log::error('Failed to send customer email notification', [
                'customer_id' => $customer->id,
                'subject' => $subject,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function sendSms(Customer $customer, string $message, array $metadata = []): void
    {
        if (! $customer->phone) {
            return;
        }

        try {
            Log::info('Customer SMS Sent', [
                'customer_id' => $customer->id,
                'phone' => $customer->phone,
                'message' => $message,
            ]);

            CommunicationLogger::log(
                subject: 'SMS Notification',
                message: $message,
                type: 'sms',
                recipient: $customer,
                metadata: $metadata
            );
        } catch (\Throwable $e) {
            Log::error('Failed to log customer SMS notification', [
                'customer_id' => $customer->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
