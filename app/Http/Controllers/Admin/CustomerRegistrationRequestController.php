<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CustomerGroup;
use App\Models\CustomerRegistrationRequest;
use App\Models\LoanProduct;
use App\Models\Customer;
use App\Notifications\CustomerRegistrationRequestRevertedNotification;
use App\Support\PublicRegistrationPaths;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Illuminate\View\View;

class CustomerRegistrationRequestController extends Controller
{
    public function index(Request $request): View
    {
        $query = CustomerRegistrationRequest::query()
            ->with(['product', 'group'])
            ->latest();

        if ($status = $request->input('status')) {
            $query->where('status', $status);
        }

        if ($search = trim((string) $request->input('search'))) {
            $query->where(function ($q) use ($search): void {
                $q->where('reference', 'like', "%{$search}%")
                    ->orWhere('first_name', 'like', "%{$search}%")
                    ->orWhere('last_name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%")
                    ->orWhere('national_id', 'like', "%{$search}%");
            });
        }

        if ($productId = $request->input('loan_product_id')) {
            $query->where('loan_product_id', (int) $productId);
        }

        if ($groupId = $request->input('customer_group_id')) {
            $query->where('customer_group_id', (int) $groupId);
        }

        $requests = $query->paginate(20)->withQueryString();

        $loanProducts = LoanProduct::query()
            ->whereIn('id', $requests->pluck('loan_product_id')->filter()->unique())
            ->get()
            ->keyBy('id');

        $customerGroups = CustomerGroup::query()
            ->whereIn('id', $requests->pluck('customer_group_id')->filter()->unique())
            ->get()
            ->keyBy('id');

        $statusOptions = [
            'pending' => 'Pending',
            'reverted' => 'Reverted (customer editing)',
            'approved' => 'Approved',
            'rejected' => 'Rejected',
        ];

        return view('admin.customer-requests.index', [
            'requests' => $requests,
            'loanProducts' => $loanProducts,
            'customerGroups' => $customerGroups,
            'statusOptions' => $statusOptions,
        ]);
    }

    public function show(CustomerRegistrationRequest $registrationRequest): View
    {
        $registrationRequest->load(['product', 'group']);

        $payload = $registrationRequest->payload ?? [];
        $kycPaths = $payload['kyc_paths'] ?? [];
        $kycDocuments = $this->resolveKycDocuments(is_array($kycPaths) ? $kycPaths : []);
        unset($payload['kyc_paths'], $payload['reference']);

        // Duplicate checks against existing customers
        $duplicateMatches = [
            'national_id' => [
                'label' => 'National ID',
                'value' => $registrationRequest->national_id,
                'customers' => collect(),
            ],
            'phone' => [
                'label' => 'Phone',
                'value' => $registrationRequest->phone,
                'customers' => collect(),
            ],
            'email' => [
                'label' => 'Email',
                'value' => $registrationRequest->email,
                'customers' => collect(),
            ],
            'next_of_kin' => [
                'label' => 'Next of kin (name / phone)',
                'value' => null,
                'customers' => collect(),
            ],
        ];

        if ($registrationRequest->national_id) {
            $duplicateMatches['national_id']['customers'] = Customer::query()
                ->where('national_id', $registrationRequest->national_id)
                ->limit(5)
                ->get();
        }

        if ($registrationRequest->phone) {
            $normalizedPhone = preg_replace('/\D/', '', $registrationRequest->phone);
            $duplicateMatches['phone']['customers'] = Customer::query()
                ->where('phone', $normalizedPhone)
                ->limit(5)
                ->get();
        }

        if ($registrationRequest->email) {
            $duplicateMatches['email']['customers'] = Customer::query()
                ->where('email', $registrationRequest->email)
                ->limit(5)
                ->get();
        }

        $nextOfKinName = $payload['next_of_kin_name'] ?? null;
        $nextOfKinPhone = $payload['next_of_kin_phone'] ?? null;
        if ($nextOfKinName || $nextOfKinPhone) {
            $duplicateMatches['next_of_kin']['value'] = trim(($nextOfKinName ?? '') . ' ' . ($nextOfKinPhone ?? ''));

            $nokQuery = Customer::query();
            $nokQuery->where(function ($q) use ($nextOfKinName, $nextOfKinPhone): void {
                if ($nextOfKinName) {
                    $q->where('next_of_kin_name', 'like', $nextOfKinName);
                }
                if ($nextOfKinPhone) {
                    $normalizedNokPhone = preg_replace('/\D/', '', $nextOfKinPhone);
                    $q->orWhere('next_of_kin_phone', $normalizedNokPhone);
                }
            });

            $duplicateMatches['next_of_kin']['customers'] = $nokQuery->limit(5)->get();
        }

        $hasDuplicates = collect($duplicateMatches)
            ->some(fn (array $entry) => $entry['customers']->isNotEmpty());

        return view('admin.customer-requests.show', [
            'request' => $registrationRequest,
            'payload' => $payload,
            'kycDocuments' => $kycDocuments,
            'duplicateMatches' => $duplicateMatches,
            'hasDuplicates' => $hasDuplicates,
        ]);
    }

    public function approve(CustomerRegistrationRequest $registrationRequest): RedirectResponse
    {
        if ($registrationRequest->created_customer_id) {
            return redirect()
                ->route('admin.customer-requests.show', $registrationRequest)
                ->with('status', 'This request has already been converted into a customer and cannot be modified.');
        }

        if ($registrationRequest->status === 'approved') {
            return redirect()
                ->route('admin.customer-requests.show', $registrationRequest)
                ->with('status', 'This request is already in review.');
        }

        $registrationRequest->status = 'approved';
        $registrationRequest->save();

        return redirect()
            ->route('admin.customer-requests.show', $registrationRequest)
            ->with('status', 'Review started. The customer can no longer edit this request until you revert it.');
    }

    public function reject(CustomerRegistrationRequest $registrationRequest): RedirectResponse
    {
        if ($registrationRequest->created_customer_id) {
            return redirect()
                ->route('admin.customer-requests.show', $registrationRequest)
                ->with('status', 'This request has already been converted into a customer and cannot be modified.');
        }

        if ($registrationRequest->status === 'rejected') {
            return redirect()
                ->route('admin.customer-requests.show', $registrationRequest)
                ->with('status', 'This request is already rejected.');
        }

        $registrationRequest->status = 'rejected';
        $registrationRequest->save();

        return redirect()
            ->route('admin.customer-requests.show', $registrationRequest)
            ->with('status', 'Customer registration request rejected.');
    }

    public function revert(Request $request, CustomerRegistrationRequest $registrationRequest): RedirectResponse
    {
        if ($registrationRequest->created_customer_id) {
            return redirect()
                ->route('admin.customer-requests.show', $registrationRequest)
                ->with('status', 'This request has already been converted into a customer and cannot be modified.');
        }

        if ($registrationRequest->status === 'reverted') {
            return redirect()
                ->route('admin.customer-requests.show', $registrationRequest)
                ->with('status', 'This request is already marked as reverted and can be edited by the customer.');
        }

        $data = $request->validate([
            'revert_reason' => ['required', 'string', 'max:2000'],
            'notify_applicant' => ['nullable', 'boolean'],
            'revert_action' => ['nullable', 'string', 'max:255'],
        ]);

        $reason = trim((string) $data['revert_reason']);
        $shouldNotify = filter_var($data['notify_applicant'] ?? false, FILTER_VALIDATE_BOOLEAN);
        if ($reason === '') {
            return back()
                ->withInput()
                ->withErrors(['revert_reason' => 'Reason / instructions is required.']);
        }

        $registrationRequest->status = 'reverted';
        $metadata = is_array($registrationRequest->approval_metadata) ? $registrationRequest->approval_metadata : [];
        $metadata['revert'] = [
            'reason' => $reason,
            'by_admin_id' => auth('admin')->id(),
            'at' => now()->toISOString(),
        ];
        $registrationRequest->approval_metadata = $metadata;
        $registrationRequest->save();

        $emailSent = false;
        $email = trim((string) ($registrationRequest->email ?? ''));
        if ($shouldNotify && $email !== '') {
            $editUrl = match ($registrationRequest->registration_path) {
                PublicRegistrationPaths::GOVERNMENT_WORKER => route('customer.register-request.government-worker.edit', $registrationRequest->reference),
                PublicRegistrationPaths::COLLATERAL_BASED => route('customer.register-request.collateral-based.edit', $registrationRequest->reference),
                default => route('customer.register-request.create'),
            };

            try {
                Notification::route('mail', $email)->notify(
                    new CustomerRegistrationRequestRevertedNotification(
                        fullName: trim("{$registrationRequest->first_name} {$registrationRequest->last_name}") ?: 'Customer',
                        reference: (string) $registrationRequest->reference,
                        registrationPathLabel: $registrationRequest->registration_path
                            ? PublicRegistrationPaths::label($registrationRequest->registration_path)
                            : ($registrationRequest->product?->name ?? 'registration'),
                        reason: $reason,
                        editUrl: $editUrl,
                    )
                );
                $emailSent = true;
            } catch (\Throwable $e) {
                Log::warning('Failed to email reverted customer registration request', [
                    'registration_request_id' => $registrationRequest->id,
                    'reference' => $registrationRequest->reference,
                    'email' => $email,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return redirect()
            ->route('admin.customer-requests.show', $registrationRequest)
            ->with('status', $emailSent
                ? 'Request reverted for editing and emailed to the applicant.'
                : 'Request reverted for editing. (No email sent)'
            );
    }

    /**
     * @param  array<int|string, mixed>  $kycPaths
     * @return list<array{label: string, path: string}>
     */
    private function resolveKycDocuments(array $kycPaths): array
    {
        $labelMap = [
            'front_image_path' => 'Front of ID',
            'back_image_path' => 'Back of ID',
            'profile_picture_path' => 'Profile Picture',
            'bank_statement_path' => 'Bank Statement',
            'payslip_path' => 'Payslip',
        ];

        $paths = array_values(array_filter($kycPaths, fn ($path) => is_string($path) && $path !== ''));

        if ($paths === []) {
            return [];
        }

        if (array_is_list($kycPaths)) {
            $legacyOrder = array_keys($labelMap);
            $documents = [];

            foreach ($paths as $index => $path) {
                $key = $legacyOrder[$index] ?? null;
                $documents[] = [
                    'label' => $key ? ($labelMap[$key] ?? 'Document '.($index + 1)) : 'Document '.($index + 1),
                    'path' => $path,
                ];
            }

            return $documents;
        }

        $documents = [];

        foreach ($labelMap as $key => $label) {
            if (! empty($kycPaths[$key]) && is_string($kycPaths[$key])) {
                $documents[] = ['label' => $label, 'path' => $kycPaths[$key]];
            }
        }

        foreach ($kycPaths as $key => $path) {
            if (! is_string($path) || $path === '' || isset($labelMap[$key])) {
                continue;
            }

            $documents[] = [
                'label' => Str::of((string) $key)->replace('_path', '')->replace('_', ' ')->title()->toString(),
                'path' => $path,
            ];
        }

        return $documents;
    }
}
