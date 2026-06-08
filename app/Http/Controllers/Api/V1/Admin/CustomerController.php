<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\Company;
use App\Models\LoanProduct;
use App\Models\CustomerGroup;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class CustomerController extends Controller
{
    /**
     * List customers with filters
     */
    public function index(Request $request): JsonResponse
    {
        $admin = $request->user();
        
        if (!$admin->can('customers.view')) {
            return response()->json([
                'success' => false,
                'message' => 'You do not have permission to view customers.',
            ], 403);
        }

        $query = Customer::with(['company', 'loanProduct', 'customerGroup']);

        // Search filter
        if ($request->has('search') && $request->search) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('first_name', 'like', "%{$search}%")
                    ->orWhere('last_name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%")
                    ->orWhere('national_id', 'like', "%{$search}%");
            });
        }

        // Status filter
        if ($request->has('status') && $request->status) {
            $query->where('status', $request->status);
        }

        // Product filter
        if ($request->has('loan_product_id') && $request->loan_product_id) {
            $query->where('loan_product_id', $request->loan_product_id);
        }

        // Customer group filter
        if ($request->has('customer_group_id') && $request->customer_group_id) {
            $query->where('customer_group_id', $request->customer_group_id);
        }

        // Company filter
        if ($request->has('company_id') && $request->company_id) {
            $query->where('company_id', $request->company_id);
        }

        // Date filters
        if ($request->has('date_from') && $request->date_from) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }

        if ($request->has('date_to') && $request->date_to) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }

        $perPage = min($request->get('per_page', 20), 100);
        $customers = $query->latest()->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => \App\Http\Resources\Api\V1\CustomerResource::collection($customers),
            'meta' => [
                'current_page' => $customers->currentPage(),
                'last_page' => $customers->lastPage(),
                'per_page' => $customers->perPage(),
                'total' => $customers->total(),
            ],
        ]);
    }

    /**
     * Show a specific customer
     */
    public function show(Customer $customer): JsonResponse
    {
        $admin = request()->user();
        
        if (!$admin->can('customers.view')) {
            return response()->json([
                'success' => false,
                'message' => 'You do not have permission to view customers.',
            ], 403);
        }

        $customer->load(['company', 'loanProduct', 'customerGroup', 'customerGroup.loanProduct']);

        return response()->json([
            'success' => true,
            'data' => new \App\Http\Resources\Api\V1\CustomerResource($customer),
        ]);
    }

    /**
     * Create a new customer
     */
    public function store(Request $request): JsonResponse
    {
        $admin = $request->user();
        
        if (!$admin->can('customers.create')) {
            return response()->json([
                'success' => false,
                'message' => 'You do not have permission to create customers.',
            ], 403);
        }

        $validated = $request->validate([
            'company_id' => ['required', 'exists:companies,id'],
            'loan_product_id' => ['required', 'exists:loan_products,id'],
            'customer_group_id' => ['nullable', 'exists:customer_groups,id'],
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:customers,email'],
            'phone' => ['required', 'string', 'max:30'],
            'date_of_birth' => ['nullable', 'date'],
            'gender' => ['nullable', 'in:male,female,other'],
            'national_id' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', 'in:pending,active,inactive'],
            'address_line1' => ['nullable', 'string', 'max:255'],
            'address_line2' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:255'],
            'state' => ['nullable', 'string', 'max:255'],
            'postal_code' => ['nullable', 'string', 'max:255'],
            'country' => ['nullable', 'string', 'max:255'],
        ]);

        // Generate default PIN
        $pin = str_pad((string) random_int(1000, 9999), 4, '0', STR_PAD_LEFT);
        $validated['password'] = Hash::make($pin);
        $validated['must_change_pin'] = true;
        $validated['status'] = $validated['status'] ?? 'pending';

        $customer = Customer::create($validated);

        $customer->load(['company', 'loanProduct', 'customerGroup']);

        return response()->json([
            'success' => true,
            'message' => 'Customer created successfully.',
            'data' => new \App\Http\Resources\Api\V1\CustomerResource($customer),
        ], 201);
    }

    /**
     * Update a customer
     */
    public function update(Request $request, Customer $customer): JsonResponse
    {
        $admin = $request->user();
        
        if (!$admin->can('customers.update')) {
            return response()->json([
                'success' => false,
                'message' => 'You do not have permission to update customers.',
            ], 403);
        }

        $validated = $request->validate([
            'company_id' => ['sometimes', 'exists:companies,id'],
            'loan_product_id' => ['sometimes', 'exists:loan_products,id'],
            'customer_group_id' => ['nullable', 'exists:customer_groups,id'],
            'first_name' => ['sometimes', 'string', 'max:255'],
            'last_name' => ['sometimes', 'string', 'max:255'],
            'email' => ['sometimes', 'email', 'max:255', Rule::unique('customers')->ignore($customer->id)],
            'phone' => ['sometimes', 'string', 'max:30'],
            'date_of_birth' => ['nullable', 'date'],
            'gender' => ['nullable', 'in:male,female,other'],
            'national_id' => ['nullable', 'string', 'max:255'],
            'status' => ['sometimes', 'in:pending,active,inactive'],
            'address_line1' => ['nullable', 'string', 'max:255'],
            'address_line2' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:255'],
            'state' => ['nullable', 'string', 'max:255'],
            'postal_code' => ['nullable', 'string', 'max:255'],
            'country' => ['nullable', 'string', 'max:255'],
        ]);

        $customer->update($validated);
        $customer->load(['company', 'loanProduct', 'customerGroup']);

        return response()->json([
            'success' => true,
            'message' => 'Customer updated successfully.',
            'data' => new \App\Http\Resources\Api\V1\CustomerResource($customer),
        ]);
    }

    /**
     * Find customer by phone number or national ID
     */
    public function findByPhoneOrNationalId(Request $request): JsonResponse
    {
        $admin = $request->user();
        
        if (!$admin->can('customers.view')) {
            return response()->json([
                'success' => false,
                'message' => 'You do not have permission to view customers.',
            ], 403);
        }

        $validated = $request->validate([
            'phone' => ['required_without:national_id', 'string'],
            'national_id' => ['required_without:phone', 'string'],
        ], [
            'phone.required_without' => 'Either phone or national_id is required.',
            'national_id.required_without' => 'Either phone or national_id is required.',
        ]);

        $query = Customer::with(['company', 'loanProduct', 'customerGroup']);

        if (isset($validated['phone'])) {
            // Normalize phone number
            $phone = preg_replace('/\D/', '', $validated['phone']);
            $query->where(function($q) use ($phone) {
                $q->where('phone', $phone)
                  ->orWhereRaw('REPLACE(REPLACE(REPLACE(phone, "+", ""), "-", ""), " ", "") = ?', [$phone]);
            });
        }

        if (isset($validated['national_id'])) {
            $query->where('national_id', $validated['national_id']);
        }

        $customer = $query->first();

        if (!$customer) {
            return response()->json([
                'success' => false,
                'message' => 'Customer not found.',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => new \App\Http\Resources\Api\V1\CustomerResource($customer),
        ]);
    }

    /**
     * Get customer's loans
     */
    public function loans(Request $request, Customer $customer): JsonResponse
    {
        $admin = $request->user();
        
        if (!$admin->can('customers.view')) {
            return response()->json([
                'success' => false,
                'message' => 'You do not have permission to view customers.',
            ], 403);
        }

        $loans = $customer->loans()
            ->with(['loanProduct', 'customerGroup', 'channel', 'approver'])
            ->orderBy('created_at', 'desc')
            ->get();

        // Calculate summary statistics
        $summary = [
            'total_loans' => $loans->count(),
            'total_principal' => $loans->sum('principal_amount'),
            'total_amount' => $loans->sum('total_amount'),
            'total_outstanding' => $loans->sum('outstanding_balance'),
            'active_loans' => $loans->whereIn('status', ['approved', 'active'])->count(),
            'completed_loans' => $loans->where('status', 'completed')->count(),
            'defaulted_loans' => $loans->where('status', 'defaulted')->count(),
        ];

        return response()->json([
            'success' => true,
            'data' => [
                'customer' => new \App\Http\Resources\Api\V1\CustomerResource($customer),
                'loans' => \App\Http\Resources\Api\V1\LoanResource::collection($loans),
                'summary' => $summary,
            ],
        ]);
    }

    /**
     * Get customer's repayments
     */
    public function repayments(Request $request, Customer $customer): JsonResponse
    {
        $admin = $request->user();
        
        if (!$admin->can('customers.view')) {
            return response()->json([
                'success' => false,
                'message' => 'You do not have permission to view customers.',
            ], 403);
        }

        $perPage = min($request->get('per_page', 20), 100);
        
        $repayments = \App\Models\Repayment::where('customer_id', $customer->id)
            ->with(['channel', 'loanRepayments.loan.loanProduct'])
            ->orderBy('created_at', 'desc')
            ->paginate($perPage);

        // Calculate summary statistics
        $allRepayments = \App\Models\Repayment::where('customer_id', $customer->id)
            ->where('status', 'completed')
            ->get();

        $summary = [
            'total_repayments' => $allRepayments->count(),
            'total_amount' => $allRepayments->sum('total_amount'),
            'total_principal' => $allRepayments->sum(function($repayment) {
                return $repayment->loanRepayments->sum('principal_amount');
            }),
            'total_interest' => $allRepayments->sum(function($repayment) {
                return $repayment->loanRepayments->sum('interest_amount');
            }),
            'total_fees' => $allRepayments->sum(function($repayment) {
                return $repayment->loanRepayments->sum('processing_fee_amount');
            }),
        ];

        return response()->json([
            'success' => true,
            'data' => [
                'customer' => new \App\Http\Resources\Api\V1\CustomerResource($customer),
                'repayments' => \App\Http\Resources\Api\V1\RepaymentResource::collection($repayments),
                'summary' => $summary,
            ],
            'meta' => [
                'current_page' => $repayments->currentPage(),
                'last_page' => $repayments->lastPage(),
                'per_page' => $repayments->perPage(),
                'total' => $repayments->total(),
            ],
        ]);
    }
}

