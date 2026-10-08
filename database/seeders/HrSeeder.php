<?php

namespace Database\Seeders;

use App\Models\ContractType;
use App\Models\ContractTypeLeaveRule;
use App\Models\Department;
use App\Models\LeaveType;
use Illuminate\Database\Seeder;

class HrSeeder extends Seeder
{
    public function run(): void
    {
        $departments = [
            ['code' => 'HR', 'name' => 'Human Resources', 'description' => 'People operations, recruitment, and employee relations.'],
            ['code' => 'ICT', 'name' => 'ICT', 'description' => 'Information and communication technology, systems, and infrastructure.'],
            ['code' => 'OPS', 'name' => 'Operations', 'description' => 'Day-to-day business operations and service delivery.'],
            ['code' => 'MGT', 'name' => 'Executive / Management', 'description' => 'Executive leadership and general management.'],
        ];

        foreach ($departments as $row) {
            Department::query()->updateOrCreate(
                ['code' => $row['code']],
                [
                    'name' => $row['name'],
                    'description' => $row['description'],
                    'is_active' => true,
                ]
            );
        }

        $leaveTypes = [
            ['code' => 'annual', 'name' => 'Annual Leave', 'accrual_based' => true],
            ['code' => 'sick', 'name' => 'Sick Leave', 'accrual_based' => false],
            ['code' => 'maternity', 'name' => 'Maternity Leave', 'accrual_based' => false],
            ['code' => 'paternity', 'name' => 'Paternity Leave', 'accrual_based' => false],
            ['code' => 'compassionate', 'name' => 'Compassionate Leave', 'accrual_based' => false],
            ['code' => 'study', 'name' => 'Study Leave', 'accrual_based' => false],
            ['code' => 'unpaid', 'name' => 'Unpaid Leave', 'is_paid' => false, 'accrual_based' => false],
        ];

        foreach ($leaveTypes as $row) {
            LeaveType::query()->updateOrCreate(
                ['code' => $row['code']],
                [
                    'name' => $row['name'],
                    'is_paid' => $row['is_paid'] ?? true,
                    'requires_attachment' => false,
                    'accrual_based' => $row['accrual_based'] ?? false,
                    'is_active' => true,
                ]
            );
        }

        $permanent = ContractType::query()->updateOrCreate(
            ['code' => 'PERMANENT'],
            [
                'name' => 'Permanent',
                'is_permanent' => true,
                'has_end_date' => false,
                'leave_accrual_enabled' => true,
                'leave_days_per_month' => 2.0,
                'renewable' => false,
                'is_active' => true,
            ]
        );

        $fixed = ContractType::query()->updateOrCreate(
            ['code' => 'FIXED_TERM'],
            [
                'name' => 'Fixed Term',
                'is_permanent' => false,
                'has_end_date' => true,
                'default_duration_months' => 12,
                'leave_accrual_enabled' => true,
                'leave_days_per_month' => 1.75,
                'renewable' => true,
                'is_active' => true,
            ]
        );

        $leaveTypeByCode = LeaveType::query()->pluck('id', 'code');

        $ruleTemplates = [
            ['code' => 'annual', 'days' => '2.00', 'accrual' => true, 'gender' => ContractTypeLeaveRule::GENDER_ALL],
            ['code' => 'sick', 'days' => '0', 'accrual' => false, 'gender' => ContractTypeLeaveRule::GENDER_ALL],
            ['code' => 'maternity', 'days' => '0', 'accrual' => false, 'gender' => ContractTypeLeaveRule::GENDER_FEMALE],
            ['code' => 'paternity', 'days' => '0', 'accrual' => false, 'gender' => ContractTypeLeaveRule::GENDER_MALE],
            ['code' => 'compassionate', 'days' => '0', 'accrual' => false, 'gender' => ContractTypeLeaveRule::GENDER_ALL],
            ['code' => 'study', 'days' => '0', 'accrual' => false, 'gender' => ContractTypeLeaveRule::GENDER_ALL],
            ['code' => 'unpaid', 'days' => '0', 'accrual' => false, 'gender' => ContractTypeLeaveRule::GENDER_ALL],
        ];

        foreach ([$permanent, $fixed] as $contractType) {
            foreach ($ruleTemplates as $template) {
                $leaveTypeId = $leaveTypeByCode->get($template['code']);
                if (! $leaveTypeId) {
                    continue;
                }
                ContractTypeLeaveRule::query()->updateOrCreate(
                    [
                        'contract_type_id' => $contractType->id,
                        'leave_type_id' => $leaveTypeId,
                    ],
                    [
                        'days_per_month' => $template['days'],
                        'requires_accrual' => $template['accrual'],
                        'applicable_gender' => $template['gender'],
                    ]
                );
            }
        }

        foreach (['TEMPORARY', 'INTERNSHIP'] as $code) {
            ContractType::query()->updateOrCreate(
                ['code' => $code],
                [
                    'name' => ucwords(strtolower(str_replace('_', ' ', $code))),
                    'is_permanent' => false,
                    'has_end_date' => true,
                    'leave_accrual_enabled' => $code !== 'INTERNSHIP',
                    'is_active' => true,
                ]
            );
        }
    }
}
