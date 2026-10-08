<?php

namespace App\Http\Controllers\Admin\Hr;

use App\Http\Controllers\Controller;
use App\Models\Department;
use App\Models\Position;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

class PositionController extends Controller
{
    public function index(): View
    {
        abort_unless(auth('admin')->user()?->can('hr.positions.view'), 403);

        $positions = Position::query()->with('department')->orderBy('name')->get();

        return view('admin.hr.positions.index', compact('positions'));
    }

    public function create(): View
    {
        abort_unless(auth('admin')->user()?->can('hr.positions.manage'), 403);

        return view('admin.hr.positions.create', $this->formOptions());
    }

    public function store(Request $request): RedirectResponse|JsonResponse
    {
        abort_unless(auth('admin')->user()?->can('hr.positions.manage'), 403);

        $validated = $this->validatePosition($request);
        $position = Position::create($validated);
        $position->load('department:id,name');

        if ($request->expectsJson()) {
            return response()->json([
                'message' => 'Position created.',
                'position' => [
                    'id' => $position->id,
                    'code' => $position->code,
                    'name' => $position->name,
                    'department_id' => $position->department_id,
                    'department_name' => $position->department?->name,
                ],
            ], 201);
        }

        return redirect()
            ->route('admin.hr.positions.index')
            ->with('status', 'Position created.');
    }

    public function edit(Position $position): View
    {
        abort_unless(auth('admin')->user()?->can('hr.positions.manage'), 403);

        return view('admin.hr.positions.edit', ['position' => $position] + $this->formOptions());
    }

    public function update(Request $request, Position $position): RedirectResponse
    {
        abort_unless(auth('admin')->user()?->can('hr.positions.manage'), 403);

        $position->update($this->validatePosition($request, $position->id));

        return redirect()->route('admin.hr.positions.index')->with('status', 'Position updated.');
    }

    /**
     * @return array<string, mixed>
     */
    private function formOptions(): array
    {
        return [
            'departments' => Department::query()->where('is_active', true)->orderBy('name')->get(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function validatePosition(Request $request, ?int $positionId = null): array
    {
        $validated = $request->validate([
            'code' => ['nullable', 'string', 'max:50', 'unique:positions,code,'.$positionId],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'department_id' => ['nullable', 'integer', 'exists:departments,id'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        if (empty($validated['code'])) {
            $base = Str::upper(Str::slug($validated['name'], '_'));
            $code = Str::limit($base, 45, '');
            $suffix = 1;
            while (Position::query()->where('code', $code)->when($positionId, fn ($q) => $q->where('id', '!=', $positionId))->exists()) {
                $code = Str::limit($base, 42, '').'_'.$suffix;
                $suffix++;
            }
            $validated['code'] = $code;
        }

        $validated['is_active'] = $request->boolean('is_active', true);

        return $validated;
    }
}
