<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\Company;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CompanyController extends Controller
{
    /**
     * List companies
     */
    public function index(Request $request): JsonResponse
    {
        $admin = $request->user();
        
        if (!$admin->can('companies.view')) {
            return response()->json([
                'success' => false,
                'message' => 'You do not have permission to view companies.',
            ], 403);
        }

        $query = Company::with(['sector', 'relationshipManager'])->withCount(['admins', 'customers']);

        if ($request->has('search') && $request->search) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('code', 'like', "%{$search}%")
                    ->orWhere('registration_number', 'like', "%{$search}%");
            });
        }

        if ($request->has('status') && $request->status) {
            $query->where('status', $request->status);
        }

        if ($request->has('type') && $request->type) {
            $query->where('type', $request->type);
        }

        $perPage = min($request->get('per_page', 20), 100);
        $companies = $query->orderByDesc('is_primary')->orderBy('name')->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => \App\Http\Resources\Api\V1\CompanyResource::collection($companies),
            'meta' => [
                'current_page' => $companies->currentPage(),
                'last_page' => $companies->lastPage(),
                'per_page' => $companies->perPage(),
                'total' => $companies->total(),
            ],
        ]);
    }

    /**
     * Show a specific company
     */
    public function show(Company $company): JsonResponse
    {
        $admin = request()->user();
        
        if (!$admin->can('companies.view')) {
            return response()->json([
                'success' => false,
                'message' => 'You do not have permission to view companies.',
            ], 403);
        }

        $company->load(['sector', 'relationshipManager', 'approver'])->loadCount(['admins', 'customers']);

        return response()->json([
            'success' => true,
            'data' => new \App\Http\Resources\Api\V1\CompanyResource($company),
        ]);
    }

    /**
     * Create a new company
     */
    public function store(Request $request): JsonResponse
    {
        $admin = $request->user();
        
        if (!$admin->can('companies.create')) {
            return response()->json([
                'success' => false,
                'message' => 'You do not have permission to create companies.',
            ], 403);
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'code' => ['required', 'string', 'max:255', 'unique:companies,code'],
            'registration_number' => ['nullable', 'string', 'max:255'],
            'tpin' => ['nullable', 'string', 'max:255'],
            'sector_id' => ['nullable', 'exists:sectors,id'],
            'relationship_manager_id' => ['nullable', 'exists:admins,id'],
            'contact_email' => ['nullable', 'email', 'max:255'],
            'contact_phone' => ['nullable', 'string', 'max:30'],
            'address_line1' => ['nullable', 'string', 'max:255'],
            'address_line2' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:255'],
            'state' => ['nullable', 'string', 'max:255'],
            'postal_code' => ['nullable', 'string', 'max:255'],
            'country' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', 'in:active,inactive,pending'],
        ]);

        $validated['type'] = 'partner';
        $validated['is_primary'] = false;
        $validated['status'] = $validated['status'] ?? 'active';

        $company = Company::create($validated);
        $company->load(['sector', 'relationshipManager']);

        return response()->json([
            'success' => true,
            'message' => 'Company created successfully.',
            'data' => new \App\Http\Resources\Api\V1\CompanyResource($company),
        ], 201);
    }

    /**
     * Update a company
     */
    public function update(Request $request, Company $company): JsonResponse
    {
        $admin = $request->user();
        
        if (!$admin->can('companies.update')) {
            return response()->json([
                'success' => false,
                'message' => 'You do not have permission to update companies.',
            ], 403);
        }

        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'code' => ['sometimes', 'string', 'max:255', Rule::unique('companies')->ignore($company->id)],
            'registration_number' => ['nullable', 'string', 'max:255'],
            'tpin' => ['nullable', 'string', 'max:255'],
            'sector_id' => ['nullable', 'exists:sectors,id'],
            'relationship_manager_id' => ['nullable', 'exists:admins,id'],
            'contact_email' => ['nullable', 'email', 'max:255'],
            'contact_phone' => ['nullable', 'string', 'max:30'],
            'address_line1' => ['nullable', 'string', 'max:255'],
            'address_line2' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:255'],
            'state' => ['nullable', 'string', 'max:255'],
            'postal_code' => ['nullable', 'string', 'max:255'],
            'country' => ['nullable', 'string', 'max:255'],
            'status' => ['sometimes', 'in:active,inactive,pending'],
        ]);

        $company->update($validated);
        $company->load(['sector', 'relationshipManager']);

        return response()->json([
            'success' => true,
            'message' => 'Company updated successfully.',
            'data' => new \App\Http\Resources\Api\V1\CompanyResource($company),
        ]);
    }
}

