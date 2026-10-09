<?php

namespace App\Services\Hr\EmployeeLoans;

use App\Models\Employee;
use App\Models\EmployeeLoan;
use App\Sms\DTOs\SmsMessage;
use App\Sms\Enums\SmsCategory;
use App\Sms\Services\SmsService;
use App\Sms\Services\SmsTemplateService;
use App\Support\CommunicationLogger;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class EmployeeLoanNotificationService
{
    public function __construct(
        private readonly SmsTemplateService $smsTemplateService,
        private readonly SmsService $smsService,
    ) {}

    public function sendApproved(EmployeeLoan $loan): void
    {
        $loan->loadMissing(['employee', 'loanRate.loanRateType']);
        $employee = $loan->employee;
        if (! $employee) {
            return;
        }

        $firstName = $this->firstName($employee);
        $subject = 'Employee loan approved — '.$loan->loan_number;
        $body = implode("\n", [
            'Dear '.$firstName.',',
            '',
            'Your employee loan application has been approved.',
            '',
            'Loan number: '.$loan->loan_number,
            'Principal: K '.number_format((float) $loan->principal_amount, 2),
            'Total repayable: K '.number_format((float) $loan->total_amount, 2),
            'First repayment: '.($loan->first_payment_date?->format('d M Y') ?? 'See HR for schedule'),
            '',
            'Disbursement will follow according to HR payroll procedures.',
            '',
            config('app.name').' HR Team',
        ]);

        $metadata = [
            'notification_type' => 'employee_loan_approved',
            'employee_loan_id' => $loan->id,
            'loan_number' => $loan->loan_number,
        ];

        $this->sendEmployeeEmail($employee, $subject, $body, $metadata);
        $this->queueEmployeeSms($employee, 'employee_loan_approved', [
            'name' => $firstName,
            'loan_number' => $loan->loan_number,
            'amount' => (float) $loan->principal_amount,
        ], 'employee_loan_approved', $metadata, $loan->id);

        $loan->update([
            'metadata' => array_merge($loan->metadata ?? [], [
                'employee_notified_approved_at' => now()->toIso8601String(),
            ]),
        ]);
    }

    public function sendDisbursed(EmployeeLoan $loan): void
    {
        $loan->loadMissing('employee');
        $employee = $loan->employee;
        if (! $employee) {
            return;
        }

        $firstName = $this->firstName($employee);
        $subject = 'Employee loan disbursed — '.$loan->loan_number;
        $disbursedAt = $loan->disbursed_at ? $loan->disbursed_at->format('d M Y, H:i') : now()->format('d M Y, H:i');
        $body = implode("\n", [
            'Dear '.$firstName.',',
            '',
            'Your employee loan has been disbursed.',
            '',
            'Loan number: '.$loan->loan_number,
            'Amount: K '.number_format((float) $loan->principal_amount, 2),
            'Disbursed at: '.$disbursedAt,
            'Reference: '.($loan->disbursement_reference ?? '—'),
            'First repayment: '.($loan->first_payment_date?->format('d M Y') ?? 'See schedule'),
            '',
            config('app.name').' HR Team',
        ]);

        $metadata = [
            'notification_type' => 'employee_loan_disbursed',
            'employee_loan_id' => $loan->id,
            'loan_number' => $loan->loan_number,
        ];

        $this->sendEmployeeEmail($employee, $subject, $body, $metadata);
        $this->queueEmployeeSms($employee, 'loan_disbursed', [
            'name' => $firstName,
            'loan_number' => $loan->loan_number,
            'amount' => (float) $loan->principal_amount,
            'due_date' => $loan->first_payment_date?->format('d M Y') ?? 'See HR',
            'reference' => $loan->disbursement_reference ?? 'N/A',
        ], 'employee_loan_disbursed', $metadata, $loan->id);

        $loan->update([
            'metadata' => array_merge($loan->metadata ?? [], [
                'employee_notified_disbursed_at' => now()->toIso8601String(),
            ]),
        ]);
    }

    private function firstName(Employee $employee): string
    {
        $parts = preg_split('/\s+/', trim($employee->full_name ?? 'Colleague'));

        return $parts[0] ?? 'Colleague';
    }

    /**
     * @param  array<string, mixed>  $metadata
     */
    private function sendEmployeeEmail(Employee $employee, string $subject, string $body, array $metadata): void
    {
        $email = $employee->email ?: $employee->personal_email;
        if (! $email) {
            return;
        }

        try {
            Mail::raw($body, function ($message) use ($email, $subject) {
                $message->to($email)->subject($subject);
            });

            CommunicationLogger::log(
                subject: $subject,
                message: $body,
                type: 'email',
                isSensitive: false,
                recipient: $employee,
                metadata: array_merge($metadata, [
                    'recipient_email' => $email,
                    'is_system_generated' => true,
                ]),
            );
        } catch (\Throwable $e) {
            Log::error('Failed to send employee loan email notification', [
                'employee_id' => $employee->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * @param  array<string, scalar|null>  $variables
     * @param  array<string, mixed>  $metadata
     */
    private function queueEmployeeSms(
        Employee $employee,
        string $templateKey,
        array $variables,
        string $messageType,
        array $metadata,
        ?int $employeeLoanId = null,
    ): void {
        if (! $employee->phone) {
            return;
        }

        try {
            $body = $this->smsTemplateService->render($templateKey, $variables);
            if ($body === null) {
                return;
            }

            $this->smsService->queueSend(new SmsMessage(
                phone: $employee->phone,
                body: $body,
                category: SmsCategory::General,
                messageType: $messageType,
                recipientType: $employee->getMorphClass(),
                recipientId: (int) $employee->id,
                metadata: array_merge($metadata, [
                    'template_key' => $templateKey,
                    'employee_loan_id' => $employeeLoanId,
                ]),
            ));

            CommunicationLogger::log(
                subject: 'SMS Notification',
                message: $body,
                type: 'sms',
                isSensitive: false,
                recipient: $employee,
                metadata: array_merge($metadata, ['template_key' => $templateKey]),
            );
        } catch (\Throwable $e) {
            Log::error('Failed to queue employee loan SMS', [
                'employee_id' => $employee->id,
                'template_key' => $templateKey,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
