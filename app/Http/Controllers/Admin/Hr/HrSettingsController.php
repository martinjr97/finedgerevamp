<?php

namespace App\Http\Controllers\Admin\Hr;

use App\Http\Controllers\Controller;
use App\Models\ContractType;
use App\Models\Department;
use App\Models\LeaveType;
use Illuminate\View\View;

class HrSettingsController extends Controller
{
    public function index(): View
    {
        abort_unless(auth('admin')->user()?->can('hr.settings.view'), 403);

        $leaveTypes = LeaveType::query()->orderBy('name')->get();
        $contractTypes = ContractType::query()->orderBy('name')->get();

        $summary = [
            'departments' => Department::query()->where('is_active', true)->count(),
            'contract_types' => $contractTypes->where('is_active', true)->count(),
            'leave_types' => $leaveTypes->where('is_active', true)->count(),
        ];

        return view('admin.hr.settings.index', compact('leaveTypes', 'contractTypes', 'summary'));
    }
}
