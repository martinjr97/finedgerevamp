<?php

namespace App\Http\Controllers\Admin\Hr;

use App\Http\Controllers\Controller;
use App\Models\Department;
use App\Models\Employee;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DepartmentController extends Controller
{
    public function index(): View
    {
        abort_unless(auth('admin')->user()?->can('hr.departments.view'), 403);

        $departments = Department::query()->with(['head', 'parent'])->withCount('employees')->orderBy('name')->get();

        return view('admin.hr.departments.index', compact('departments'));
    }

    public function create(): View
    {
        abort_unless(auth('admin')->user()?->can('hr.departments.manage'), 403);

        return view('admin.hr.departments.create', $this->options());
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless(auth('admin')->user()?->can('hr.departments.manage'), 403);

        $validated = $this->validateDepartment($request);
        $department = Department::create($validated);

        return redirect()->route('admin.hr.departments.show', $department)->with('status', 'Department created.');
    }

    public function show(Department $department): View
    {
        abort_unless(auth('admin')->user()?->can('hr.departments.view'), 403);

        $department->load([
            'head',
            'parent',
            'children' => fn ($query) => $query->with('head')->withCount('employees')->orderBy('name'),
            'positions' => fn ($query) => $query->where('is_active', true)->orderBy('name'),
            'employees' => fn ($query) => $query
                ->with(['position', 'activeContract.contractType'])
                ->orderBy('first_name')
                ->orderBy('last_name'),
        ]);

        $employeeCollection = $department->employees;
        $activeCount = $employeeCollection->where('employment_status', Employee::EMPLOYMENT_ACTIVE)->count();
        $stats = [
            'employees_total' => $employeeCollection->count(),
            'employees_active' => $activeCount,
            'employees_inactive' => $employeeCollection->count() - $activeCount,
            'positions' => $department->positions->count(),
            'sub_departments' => $department->children->count(),
        ];

        return view('admin.hr.departments.show', compact('department', 'stats'));
    }

    public function edit(Department $department): View
    {
        abort_unless(auth('admin')->user()?->can('hr.departments.manage'), 403);

        return view('admin.hr.departments.edit', ['department' => $department] + $this->options());
    }

    public function update(Request $request, Department $department): RedirectResponse
    {
        abort_unless(auth('admin')->user()?->can('hr.departments.manage'), 403);

        $department->update($this->validateDepartment($request, $department->id));

        return redirect()->route('admin.hr.departments.show', $department)->with('status', 'Department updated.');
    }

    private function options(): array
    {
        return [
            'departments' => Department::query()->orderBy('name')->get(),
            'employees' => Employee::query()->active()->orderBy('first_name')->get(),
        ];
    }

    private function validateDepartment(Request $request, ?int $id = null): array
    {
        return $request->validate([
            'code' => ['required', 'string', 'max:50', 'unique:departments,code,'.$id],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'parent_department_id' => ['nullable', 'integer', 'exists:departments,id'],
            'head_employee_id' => ['nullable', 'integer', 'exists:employees,id'],
            'is_active' => ['nullable', 'boolean'],
        ]) + ['is_active' => $request->boolean('is_active', true)];
    }
}
