<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\DuplicateAlert;
use App\Support\DuplicateDetectionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class FraudProtectionController extends Controller
{
    /**
     * Display the duplicate detection dashboard
     */
    public function index(): View
    {
        abort_unless(auth('admin')->user()?->can('fraud-protection.view'), 403);

        $customersWithDuplicates = DuplicateDetectionService::getAllCustomersWithDuplicates();
        $statistics = DuplicateDetectionService::getStatistics();

        return view('admin.fraud-protection.index', [
            'customersWithDuplicates' => $customersWithDuplicates,
            'statistics' => $statistics,
        ]);
    }

    /**
     * Show duplicate details for a specific customer
     */
    public function show(Customer $customer): View
    {
        abort_unless(auth('admin')->user()?->can('fraud-protection.view'), 403);

        $duplicateInfo = DuplicateDetectionService::detectDuplicates($customer);

        return view('admin.fraud-protection.show', [
            'customer' => $customer,
            'duplicateInfo' => $duplicateInfo,
        ]);
    }

    /**
     * Clear a specific duplicate alert (mark as legitimate/false positive)
     */
    public function clearDuplicate(Request $request, Customer $customer): RedirectResponse
    {
        abort_unless(auth('admin')->user()?->can('fraud-protection.clear'), 403);

        $request->validate([
            'duplicate_customer_id' => ['required', 'exists:customers,id'],
            'match_type' => ['required', 'in:same_nrc,same_phone,same_bank_account,same_device_ip'],
            'match_value' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        try {
            DuplicateAlert::updateOrCreate(
                [
                    'customer_id' => $customer->id,
                    'duplicate_customer_id' => $request->duplicate_customer_id,
                    'match_type' => $request->match_type,
                    'match_value' => $request->match_value,
                ],
                [
                    'notes' => $request->notes,
                    'cleared_by' => auth('admin')->id(),
                    'cleared_at' => now(),
                ]
            );

            return redirect()
                ->route('admin.fraud-protection.show', $customer)
                ->with('status', 'Duplicate alert cleared successfully. This match will no longer appear.');
        } catch (\Exception $e) {
            return redirect()
                ->route('admin.fraud-protection.show', $customer)
                ->with('error', 'Failed to clear duplicate alert: ' . $e->getMessage());
        }
    }

    /**
     * Clear all duplicate alerts for a customer
     */
    public function clearAllDuplicates(Request $request, Customer $customer): RedirectResponse
    {
        abort_unless(auth('admin')->user()?->can('fraud-protection.clear'), 403);

        $request->validate([
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        try {
            $duplicateInfo = DuplicateDetectionService::detectDuplicates($customer);
            $adminId = auth('admin')->id();

            // Clear all current duplicates
            foreach ($duplicateInfo['duplicates'] as $matchType => $duplicates) {
                foreach ($duplicates as $duplicate) {
                    // Determine match_value based on match type
                    $matchValue = null;
                    if ($matchType === 'same_nrc' && isset($customer->national_id)) {
                        $matchValue = $customer->national_id;
                    } elseif ($matchType === 'same_phone' && isset($customer->phone)) {
                        $matchValue = $customer->phone;
                    } elseif (isset($duplicate['match_details'])) {
                        $matchValue = $duplicate['match_details']['ip'] ?? $duplicate['match_details']['device_name'] ?? null;
                    }

                    DuplicateAlert::updateOrCreate(
                        [
                            'customer_id' => $customer->id,
                            'duplicate_customer_id' => $duplicate['id'],
                            'match_type' => $matchType,
                            'match_value' => $matchValue,
                        ],
                        [
                            'notes' => $request->notes ?? 'All duplicates cleared at once',
                            'cleared_by' => $adminId,
                            'cleared_at' => now(),
                        ]
                    );
                }
            }

            return redirect()
                ->route('admin.fraud-protection.show', $customer)
                ->with('status', 'All duplicate alerts cleared successfully.');
        } catch (\Exception $e) {
            return redirect()
                ->route('admin.fraud-protection.show', $customer)
                ->with('error', 'Failed to clear duplicate alerts: ' . $e->getMessage());
        }
    }
}
