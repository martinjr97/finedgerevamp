<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\Loan;
use App\Models\Repayment;
use App\Models\SupportTicket;
use App\Models\Communication;
use App\Sms\Services\SmsTemplateService;
use App\Support\CommunicationLogger;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class CustomerNotificationService
{
    public function __construct(
        private readonly SmsTemplateService $smsTemplateService,
    ) {}

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

        $metadata = [
            'notification_type' => 'repayment_completed',
            'repayment_id' => $repayment->id,
            'repayment_number' => $repayment->repayment_number,
            'completion_source' => $completionSource,
            'channel' => $repayment->channel?->name,
        ];

        $this->sendEmail($customer, $subject, $emailMessage, $metadata);

        $templateKey = $totalOutstanding <= 0.00001
            ? 'repayment_success_full'
            : 'repayment_success_partial';

        $this->queueTemplateSms($customer, $templateKey, [
            'name' => $customer->first_name,
            'amount' => (float) $repayment->total_amount,
            'balance' => $totalOutstanding,
            'repayment_number' => $repayment->repayment_number,
        ], 'repayment_completed', $metadata);
    }

    public function sendRepaymentFailed(Repayment $repayment, string $failureSource = 'gateway'): void
    {
        $repayment->loadMissing(['customer', 'channel']);
        $customer = $repayment->customer;

        if (! $customer) {
            return;
        }

        $metadata = $repayment->metadata ?? [];
        if (! empty($metadata['sms_repayment_failed_sent_at'])) {
            return;
        }

        $outstanding = (float) $customer->getTotalOutstandingBalance();
        $subject = 'Repayment Not Completed - '.$repayment->repayment_number;
        $emailMessage = implode("\n", [
            'Dear '.$customer->first_name.',',
            '',
            'Your repayment could not be completed.',
            '',
            'Repayment number: '.$repayment->repayment_number,
            'Amount: ZMW '.number_format((float) $repayment->total_amount, 2),
            'Status: '.($repayment->status_message ?? 'Failed'),
            '',
            'Outstanding balance: ZMW '.number_format($outstanding, 2),
            '',
            'Please try again or contact support if you need assistance.',
            '',
            config('app.name').' Team',
        ]);

        $notificationMetadata = [
            'notification_type' => 'repayment_failed',
            'repayment_id' => $repayment->id,
            'repayment_number' => $repayment->repayment_number,
            'failure_source' => $failureSource,
        ];

        $this->sendEmail($customer, $subject, $emailMessage, $notificationMetadata);

        $this->queueTemplateSms($customer, 'repayment_failed', [
            'name' => $customer->first_name,
            'amount' => (float) $repayment->total_amount,
            'balance' => $outstanding,
            'repayment_number' => $repayment->repayment_number,
        ], 'repayment_failed', $notificationMetadata);

        $repayment->update([
            'metadata' => array_merge($metadata, [
                'sms_repayment_failed_sent_at' => now()->toIso8601String(),
            ]),
        ]);
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

        $metadata = [
            'notification_type' => 'loan_disbursed',
            'loan_id' => $loan->id,
            'loan_number' => $loan->loan_number,
            'principal_amount' => (float) $loan->principal_amount,
        ];

        $this->sendEmail($customer, $subject, $emailMessage, $metadata);

        $this->queueTemplateSms($customer, 'loan_disbursed', [
            'name' => $customer->first_name,
            'loan_number' => $loan->loan_number,
            'amount' => (float) $loan->principal_amount,
            'due_date' => $loan->first_payment_date?->format('d M Y') ?? 'See dashboard',
            'reference' => $loan->disbursement_reference ?? 'N/A',
        ], 'loan_disbursed', $metadata, $loan->id);
    }

    public function sendCustomerApprovedSms(Customer $customer, string $pin): void
    {
        $this->queueTemplateSms($customer, 'customer_approved', [
            'name' => $customer->first_name,
            'phone' => $customer->phone ?? '',
            'pin' => $pin,
        ], 'customer_approved', [
            'notification_type' => 'customer_approved',
            'customer_id' => $customer->id,
        ]);
    }

    public function sendAdminPinResetSms(Customer $customer, string $pin): void
    {
        $this->queueTemplateSms($customer, 'pin_reset_admin', [
            'name' => $customer->first_name,
            'phone' => $customer->phone ?? '',
            'pin' => $pin,
        ], 'pin_reset_admin', [
            'notification_type' => 'pin_reset_admin',
            'customer_id' => $customer->id,
        ]);
    }

    public function sendRepaymentReminderSms(
        Customer $customer,
        string $reminderType,
        Loan $loan,
        float $amount,
        string $dueDate,
        int $daysOverdue = 0,
    ): void {
        $templateKey = $this->smsTemplateService->reminderTemplateKey($reminderType);

        $this->queueTemplateSms($customer, $templateKey, [
            'name' => $customer->first_name,
            'loan_number' => $loan->loan_number,
            'amount' => $amount,
            'due_date' => $dueDate,
            'days_overdue' => (string) max(0, $daysOverdue),
        ], 'repayment_reminder_'.$reminderType, [
            'notification_type' => 'repayment_reminder',
            'reminder_type' => $reminderType,
            'loan_id' => $loan->id,
        ], $loan->id);
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
        $this->sendRawSms($customer, $smsMessage, 'loan_payment_details_changed', $metadata, $loan->id);
    }

    public function sendSupportTicketStatusChanged(
        SupportTicket $ticket,
        Customer $customer,
        string $previousStatus,
        string $newStatus,
        ?string $staffComment = null,
        ?\App\Models\Admin $updatedBy = null,
    ): void {
        if ($previousStatus === $newStatus) {
            return;
        }

        $previousLabel = ucwords(str_replace('_', ' ', $previousStatus));
        $newLabel = ucwords(str_replace('_', ' ', $newStatus));
        $ticketUrl = route('customer.support-tickets.show', $ticket);
        $subject = 'Support ticket #'.$ticket->id.' updated';

        $messageLines = [
            'Dear '.$customer->first_name.',',
            '',
            'Your support request "'.$ticket->subject.'" has been updated.',
            '',
            'Status: '.$previousLabel.' → '.$newLabel,
        ];

        if (filled($staffComment)) {
            $messageLines[] = '';
            $messageLines[] = 'Message from our team:';
            $messageLines[] = $staffComment;
        }

        $messageLines[] = '';
        $messageLines[] = 'View your ticket: '.$ticketUrl;
        $messageLines[] = '';
        $messageLines[] = config('app.name').' Team';

        $message = implode("\n", $messageLines);

        $metadata = [
            'notification_type' => 'support_ticket_status_changed',
            'support_ticket_id' => $ticket->id,
            'previous_status' => $previousStatus,
            'new_status' => $newStatus,
            'ticket_url' => $ticketUrl,
        ];

        if ($customer->email) {
            try {
                $customer->notify(new \App\Notifications\SupportTicketStatusChangedNotification(
                    $ticket,
                    $customer,
                    $previousStatus,
                    $newStatus,
                    $staffComment,
                    $updatedBy
                ));
            } catch (\Throwable $e) {
                Log::error('Failed to queue support ticket status email', [
                    'customer_id' => $customer->id,
                    'support_ticket_id' => $ticket->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $this->logCustomerInAppNotification($customer, $subject, $message, $metadata, $updatedBy?->id);
    }

    /**
     * @param  array<string, mixed>  $metadata
     */
    private function logCustomerInAppNotification(
        Customer $customer,
        string $subject,
        string $message,
        array $metadata = [],
        ?int $createdBy = null,
    ): void {
        try {
            Communication::create([
                'subject' => $subject,
                'message' => $message,
                'type' => 'email',
                'filters' => ['customer_id' => $customer->id, 'system_generated' => true],
                'recipients_count' => 1,
                'sent_count' => 1,
                'failed_count' => 0,
                'status' => 'completed',
                'sent_at' => now(),
                'created_by' => $createdBy,
                'is_sensitive' => false,
                'metadata' => array_merge($metadata, [
                    'recipient' => [
                        'type' => Customer::class,
                        'id' => $customer->id,
                        'email' => $customer->email,
                        'phone' => $customer->phone,
                        'name' => $customer->full_name,
                    ],
                    'is_system_generated' => true,
                    'delivery_channels' => ['email', 'in_app'],
                ]),
            ]);
        } catch (\Throwable $e) {
            Log::error('Failed to log customer in-app notification', [
                'customer_id' => $customer->id,
                'subject' => $subject,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * @param  array<string, scalar|null>  $variables
     * @param  array<string, mixed>  $metadata
     */
    private function queueTemplateSms(
        Customer $customer,
        string $templateKey,
        array $variables,
        string $messageType,
        array $metadata = [],
        ?int $loanId = null,
    ): void {
        if (! $customer->phone) {
            return;
        }

        try {
            $body = $this->smsTemplateService->render($templateKey, $variables);
            if ($body === null) {
                return;
            }

            $record = $this->smsTemplateService->queueForCustomer(
                $customer,
                $templateKey,
                $variables,
                $messageType,
                $metadata,
                $loanId,
            );

            if ($record) {
                $isSensitive = $this->smsTemplateService->categoryForKey($templateKey)->isSensitive();
                CommunicationLogger::log(
                    subject: 'SMS Notification',
                    message: $body,
                    type: 'sms',
                    isSensitive: $isSensitive,
                    recipient: $customer,
                    metadata: array_merge($metadata, [
                        'template_key' => $templateKey,
                        'sms_message_id' => $record->id,
                    ]),
                );
            }
        } catch (\Throwable $e) {
            Log::error('Failed to queue customer SMS notification', [
                'customer_id' => $customer->id,
                'template_key' => $templateKey,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * @param  array<string, mixed>  $metadata
     */
    private function sendRawSms(
        Customer $customer,
        string $message,
        string $messageType,
        array $metadata = [],
        ?int $loanId = null,
    ): void {
        if (! $customer->phone) {
            return;
        }

        try {
            app(\App\Sms\Services\SmsService::class)->queueSend([
                'phone' => $customer->phone,
                'body' => $message,
                'category' => 'general',
                'message_type' => $messageType,
                'recipient' => $customer,
                'customer_id' => $customer->id,
                'loan_id' => $loanId,
                'metadata' => $metadata,
            ]);

            CommunicationLogger::log(
                subject: 'SMS Notification',
                message: $message,
                type: 'sms',
                recipient: $customer,
                metadata: $metadata,
            );
        } catch (\Throwable $e) {
            Log::error('Failed to queue raw customer SMS', [
                'customer_id' => $customer->id,
                'error' => $e->getMessage(),
            ]);
        }
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
}
