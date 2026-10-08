<?php

namespace App\Services\Hr;

use App\Models\EmployeeLeaveTransaction;
use App\Models\LeaveApplication;
use Illuminate\Support\Facades\DB;

class LeaveApplicationService
{
    public function __construct(
        private readonly LeaveDurationCalculator $durationCalculator,
        private readonly LeaveBalanceService $leaveBalanceService,
    ) {}

    public function approve(LeaveApplication $application, int $approverAdminId, ?string $comments = null): LeaveApplication
    {
        if ($application->status !== LeaveApplication::STATUS_PENDING) {
            throw new \InvalidArgumentException('Only pending leave can be approved.');
        }

        $application->loadMissing('leaveType');
        $leaveType = $application->leaveType;
        if ($leaveType && $leaveType->requiresBalanceCheck()) {
            $check = $this->leaveBalanceService->validateLeaveRequest(
                $application->employee_id,
                $leaveType,
                (float) $application->days_requested,
            );
            if (! $check['ok']) {
                throw new \InvalidArgumentException($check['message'] ?? 'Insufficient leave balance.');
            }
        }

        return DB::transaction(function () use ($application, $approverAdminId, $comments) {
            $application->update([
                'status' => LeaveApplication::STATUS_APPROVED,
                'approver_id' => $approverAdminId,
                'approved_at' => now(),
                'approver_comments' => $comments,
            ]);

            EmployeeLeaveTransaction::create([
                'employee_id' => $application->employee_id,
                'leave_type_id' => $application->leave_type_id,
                'transaction_date' => $application->start_date,
                'type' => EmployeeLeaveTransaction::TYPE_LEAVE_TAKEN,
                'days' => $application->days_requested,
                'reference_type' => LeaveApplication::class,
                'reference_id' => $application->id,
                'description' => 'Leave approved',
                'created_by' => $approverAdminId,
            ]);

            return $application->fresh();
        });
    }

    public function reject(LeaveApplication $application, int $approverAdminId, ?string $comments = null): LeaveApplication
    {
        if ($application->status !== LeaveApplication::STATUS_PENDING) {
            throw new \InvalidArgumentException('Only pending leave can be rejected.');
        }

        $application->update([
            'status' => LeaveApplication::STATUS_REJECTED,
            'approver_id' => $approverAdminId,
            'approved_at' => now(),
            'approver_comments' => $comments,
        ]);

        return $application->fresh();
    }

    public function cancelApproved(LeaveApplication $application, int $adminId, ?string $reason = null): LeaveApplication
    {
        if ($application->status !== LeaveApplication::STATUS_APPROVED) {
            throw new \InvalidArgumentException('Only approved leave can be cancelled.');
        }

        return DB::transaction(function () use ($application, $adminId, $reason) {
            EmployeeLeaveTransaction::create([
                'employee_id' => $application->employee_id,
                'leave_type_id' => $application->leave_type_id,
                'transaction_date' => now()->toDateString(),
                'type' => EmployeeLeaveTransaction::TYPE_REVERSAL,
                'days' => $application->days_requested,
                'reference_type' => LeaveApplication::class,
                'reference_id' => $application->id,
                'description' => $reason ?? 'Approved leave cancelled',
                'created_by' => $adminId,
            ]);

            $application->update(['status' => LeaveApplication::STATUS_CANCELLED]);

            return $application->fresh();
        });
    }
}
