<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class EmployeeController extends Controller
{
    public function index(): View
    {
        abort_unless(auth('admin')->user()?->can('assets.view'), 403);

        $employees = Employee::query()
            ->orderBy('first_name')
            ->orderBy('last_name')
            ->get();

        return view('admin.employees.index', compact('employees'));
    }

    public function create(): View
    {
        abort_unless(auth('admin')->user()?->can('assets.create'), 403);

        return view('admin.employees.create');
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless(auth('admin')->user()?->can('assets.create'), 403);

        $validated = $request->validate([
            'employee_number' => ['nullable', 'string', 'max:50', 'unique:employees,employee_number'],
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'email', 'max:255'],
            'department' => ['nullable', 'string', 'max:255'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        Employee::create([
            'employee_number' => $validated['employee_number'] ?? null,
            'first_name' => $validated['first_name'],
            'last_name' => $validated['last_name'],
            'phone' => $validated['phone'] ?? null,
            'email' => $validated['email'] ?? null,
            'department' => $validated['department'] ?? null,
            'is_active' => $request->boolean('is_active', true),
        ]);

        return redirect()->route('admin.employees.index')
            ->with('status', 'Employee added successfully.');
    }

    public function edit(Employee $employee): View
    {
        abort_unless(auth('admin')->user()?->can('assets.update'), 403);

        return view('admin.employees.edit', compact('employee'));
    }

    public function update(Request $request, Employee $employee): RedirectResponse
    {
        abort_unless(auth('admin')->user()?->can('assets.update'), 403);

        $validated = $request->validate([
            'employee_number' => ['nullable', 'string', 'max:50', 'unique:employees,employee_number,'.$employee->id],
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'email', 'max:255'],
            'department' => ['nullable', 'string', 'max:255'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $employee->update([
            'employee_number' => $validated['employee_number'] ?? null,
            'first_name' => $validated['first_name'],
            'last_name' => $validated['last_name'],
            'phone' => $validated['phone'] ?? null,
            'email' => $validated['email'] ?? null,
            'department' => $validated['department'] ?? null,
            'is_active' => $request->boolean('is_active'),
        ]);

        return redirect()->route('admin.employees.index')
            ->with('status', 'Employee updated successfully.');
    }

    public function destroy(Employee $employee): RedirectResponse
    {
        abort_unless(auth('admin')->user()?->can('assets.delete'), 403);

        $employee->delete();

        return redirect()->route('admin.employees.index')
            ->with('status', 'Employee removed successfully.');
    }
}
