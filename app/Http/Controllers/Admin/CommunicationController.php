<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Communication;
use App\Models\Customer;
use App\Models\LoanProduct;
use App\Models\Province;
use App\Models\SmsMessage;
use App\Sms\Services\SmsService;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\View\View;
use RuntimeException;

class CommunicationController extends Controller
{
    /**
     * Display a listing of communications.
     */
    public function index(): View
    {
        abort_unless(auth('admin')->user()?->can('communications.view'), 403);

        $admin = auth('admin')->user();
        $companyFilterId = $admin->getCompanyFilterId();

        $query = Communication::with('creator');

        // Filter by company if not primary company admin
        // Communications are filtered by the creator's company
        if ($companyFilterId !== null) {
            $query->whereHas('creator', function ($q) use ($companyFilterId) {
                $q->where('company_id', $companyFilterId);
            });
        }

        $communications = $query->orderBy('created_at', 'desc')->paginate(20);

        return view('admin.communications.index', compact('communications'));
    }

    /**
     * Show the form for creating a new communication.
     */
    public function create(): View
    {
        abort_unless(auth('admin')->user()?->can('communications.create'), 403);

        $admin = auth('admin')->user();
        $companyFilterId = $admin->getCompanyFilterId();

        $productsQuery = LoanProduct::where('is_active', true);
        
        // Filter products by company if not primary company admin
        if ($companyFilterId !== null) {
            $productsQuery->where('company_id', $companyFilterId);
        }

        $products = $productsQuery->orderBy('name')->get();
        $provinces = Province::where('is_active', true)->orderBy('name')->get();

        return view('admin.communications.create', compact('products', 'provinces'));
    }

    /**
     * Store a newly created communication and send it.
     */
    public function store(Request $request): RedirectResponse
    {
        abort_unless(auth('admin')->user()?->can('communications.send'), 403);
        $validated = $request->validate([
            'subject' => ['required_if:type,email,both', 'nullable', 'string', 'max:255'],
            'message' => ['required', 'string', 'max:5000'],
            'type' => ['required', 'in:sms,email,both'],
            'filters' => ['nullable', 'array'],
            'filters.product_id' => ['nullable', 'exists:loan_products,id'],
            'filters.province_id' => ['nullable', 'exists:provinces,id'],
            'filters.age_group' => ['nullable', 'in:18-25,26-35,36-45,46-55,56-65,65+'],
            'filters.has_active_loans' => ['nullable', 'in:with,without'],
            'filters.gender' => ['nullable', 'in:male,female,other'],
        ]);

        try {
            DB::beginTransaction();

            // Get filtered customers
            $customers = $this->getFilteredCustomers($validated['filters'] ?? []);

            if ($customers->isEmpty()) {
                return redirect()->back()
                    ->withInput()
                    ->with('error', 'No customers match the selected filters. Please adjust your filters and try again.');
            }

            // Create communication record
            $subject = $this->resolveSubject($validated['type'], $validated['subject'] ?? null);
            $communication = Communication::create([
                'subject' => $subject,
                'message' => $validated['message'],
                'type' => $validated['type'],
                'filters' => $validated['filters'] ?? [],
                'recipients_count' => $customers->count(),
                'status' => 'sending',
                'created_by' => auth('admin')->id(),
            ]);

            // Send communications
            $sentCount = 0;
            $failedCount = 0;
            $errors = [];
            $smsMessageIds = [];

            foreach ($customers as $customer) {
                try {
                    if (in_array($validated['type'], ['email', 'both'])) {
                        if ($customer->email) {
                            $this->sendEmail($customer, $subject, $validated['message']);
                            $sentCount++;
                        } else {
                            $failedCount++;
                            $errors[] = "Customer {$customer->full_name} has no email address";
                        }
                    }

                    if (in_array($validated['type'], ['sms', 'both'])) {
                        if ($customer->phone) {
                            $sms = $this->sendSms($customer, $validated['message'], $communication->id);
                            $smsMessageIds[] = $sms->id;
                            if ($validated['type'] === 'sms') {
                                $sentCount++;
                            }
                        } else {
                            $failedCount++;
                            $errors[] = "Customer {$customer->full_name} has no phone number";
                        }
                    }
                } catch (\Exception $e) {
                    $failedCount++;
                    $errors[] = "Failed to send to {$customer->full_name}: " . $e->getMessage();
                    Log::error('Communication sending error', [
                        'communication_id' => $communication->id,
                        'customer_id' => $customer->id,
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            // Update communication status
            $communication->update([
                'sent_count' => $sentCount,
                'failed_count' => $failedCount,
                'status' => $failedCount > 0 && $sentCount === 0 ? 'failed' : 'completed',
                'sent_at' => now(),
                'error_message' => !empty($errors) ? implode('; ', array_slice($errors, 0, 10)) : null,
                'metadata' => array_filter([
                    'sms_message_ids' => $smsMessageIds ?: null,
                ]),
            ]);

            DB::commit();

            return redirect()->route('admin.communications.show', $communication)
                ->with('success', "Communication sent successfully! Sent: {$sentCount}, Failed: {$failedCount}");

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Communication creation error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return redirect()->back()
                ->withInput()
                ->with('error', 'Failed to send communication: ' . $e->getMessage());
        }
    }

    /**
     * Display the specified communication.
     */
    public function show(Communication $communication): View
    {
        abort_unless(auth('admin')->user()?->can('communications.view'), 403);
        
        $admin = auth('admin')->user();
        $companyFilterId = $admin->getCompanyFilterId();

        // Ensure non-primary admins can only view communications from their company
        if ($companyFilterId !== null) {
            $communication->load('creator');
            if ($communication->creator && $communication->creator->company_id != $companyFilterId) {
                abort(403, 'You can only view communications from your company.');
            }
        }

        $communication->load('creator');

        // Get recipients based on filters or metadata
        $recipients = collect();
        
        // Check if this is a system-generated communication (password reset, OTP, etc.)
        if (!empty($communication->metadata['is_system_generated']) && !empty($communication->metadata['recipient'])) {
            // System-generated communication - get recipient from metadata
            $recipientData = $communication->metadata['recipient'];
            $recipientType = $recipientData['type'] ?? null;
            $recipientId = $recipientData['id'] ?? null;
            
            if ($recipientType && $recipientId) {
                if ($recipientType === 'App\Models\Customer') {
                    $customer = Customer::find($recipientId);
                    if ($customer) {
                        // Ensure customer belongs to admin's company if not primary
                        if ($companyFilterId === null || $customer->company_id == $companyFilterId) {
                            $recipients = collect([$customer]);
                        }
                    }
                } elseif ($recipientType === 'App\Models\Admin') {
                    // For admin recipients, we don't show them in the recipients table
                    // as they're not customers. The recipient info is shown in metadata section.
                    $recipients = collect();
                }
            }
        } elseif (!empty($communication->filters['customer_id'])) {
            // Single customer communication (legacy format)
            $customer = Customer::find($communication->filters['customer_id']);
            if ($customer) {
                // Ensure customer belongs to admin's company if not primary
                if ($companyFilterId === null || $customer->company_id == $companyFilterId) {
                    $recipients = collect([$customer]);
                }
            }
        } elseif (!empty($communication->filters)) {
            // Bulk communication - get customers based on filters
            $recipients = $this->getFilteredCustomers($communication->filters);
        }
        // If filters is null/empty and not system-generated, recipients stays empty

        $smsMessages = collect();
        $smsMessageIds = data_get($communication->metadata, 'sms_message_ids', []);
        if (is_array($smsMessageIds) && $smsMessageIds !== []) {
            $smsMessages = SmsMessage::query()
                ->whereIn('id', $smsMessageIds)
                ->orderBy('id')
                ->get();
        }

        return view('admin.communications.show', compact('communication', 'recipients', 'smsMessages'));
    }

    /**
     * Get filtered customers based on applied filters.
     */
    private function getFilteredCustomers(array $filters)
    {
        $admin = auth('admin')->user();
        $companyFilterId = $admin->getCompanyFilterId();

        $query = Customer::where('status', 'active');

        // Filter by company if not primary company admin
        if ($companyFilterId !== null) {
            $query->where('company_id', $companyFilterId);
        }

        // Filter by product type
        if (!empty($filters['product_id'])) {
            $query->where('loan_product_id', $filters['product_id']);
        }

        // Filter by province
        if (!empty($filters['province_id'])) {
            $query->where('work_province_id', $filters['province_id']);
        }

        // Filter by age group
        if (!empty($filters['age_group'])) {
            $ageRange = $this->getAgeRange($filters['age_group']);
            $minDate = now()->subYears($ageRange['max'])->startOfYear();
            $maxDate = now()->subYears($ageRange['min'])->endOfYear();
            $query->whereBetween('date_of_birth', [$maxDate, $minDate]);
        }

        // Filter by active loans
        if (!empty($filters['has_active_loans'])) {
            if ($filters['has_active_loans'] === 'with') {
                $query->whereHas('loans', function ($q) {
                    $q->whereIn('status', ['approved', 'active']);
                });
            } elseif ($filters['has_active_loans'] === 'without') {
                $query->whereDoesntHave('loans', function ($q) {
                    $q->whereIn('status', ['approved', 'active']);
                });
            }
        }

        // Filter by gender
        if (!empty($filters['gender'])) {
            $query->where('gender', $filters['gender']);
        }

        return $query->get();
    }

    /**
     * Get age range from age group string.
     */
    private function getAgeRange(string $ageGroup): array
    {
        return match ($ageGroup) {
            '18-25' => ['min' => 18, 'max' => 25],
            '26-35' => ['min' => 26, 'max' => 35],
            '36-45' => ['min' => 36, 'max' => 45],
            '46-55' => ['min' => 46, 'max' => 55],
            '56-65' => ['min' => 56, 'max' => 65],
            '65+' => ['min' => 65, 'max' => 120],
            default => ['min' => 0, 'max' => 120],
        };
    }

    /**
     * Send email to customer.
     */
    private function sendEmail(Customer $customer, string $subject, string $message): void
    {
        Mail::raw($message, function ($mail) use ($customer, $subject) {
            $mail->to($customer->email, $customer->full_name)
                ->subject($subject);
        });
    }

    /**
     * Deliver SMS immediately via the configured provider and return the audit record.
     * Status reflects the real provider outcome (e.g. Zamtel accepted/rejected).
     */
    private function sendSms(Customer $customer, string $message, ?int $communicationId = null): SmsMessage
    {
        $record = app(SmsService::class)->sendRecorded([
            'phone' => $customer->phone,
            'body' => $message,
            'category' => 'general',
            'message_type' => 'admin_communication',
            'recipient' => $customer,
            'customer_id' => $customer->id,
            'metadata' => array_filter([
                'source' => 'admin_communication',
                'communication_id' => $communicationId,
            ]),
        ]);

        if ($record === null) {
            throw new RuntimeException('SMS could not be sent (missing phone).');
        }

        if ($record->status === 'sent') {
            return $record;
        }

        $providerMessage = data_get($record->provider_response, 'message');
        $reason = $record->skip_reason
            ?: (is_string($providerMessage) ? $providerMessage : null)
            ?: $record->status;

        throw new RuntimeException('SMS was not delivered: '.$reason);
    }

    /**
     * Build a list-friendly subject when the operator did not supply one (SMS).
     */
    private function resolveSubject(string $type, ?string $subject, ?Customer $customer = null): string
    {
        $trimmed = trim((string) $subject);
        if ($trimmed !== '') {
            return $trimmed;
        }

        $name = $customer?->full_name;

        return match ($type) {
            'sms' => $name ? "SMS to {$name}" : 'SMS notification',
            'both' => $name ? "Message to {$name}" : 'Customer notification',
            default => 'Email notification',
        };
    }

    /**
     * Create a communication record for system-generated messages (PIN reset, OTP, etc.)
     */
    public static function logSystemCommunication(
        Customer $customer,
        string $subject,
        string $message,
        string $type = 'email',
        bool $isSensitive = false,
        ?int $createdBy = null
    ): Communication {
        return Communication::create([
            'subject' => $subject,
            'message' => $message,
            'type' => $type,
            'filters' => ['customer_id' => $customer->id, 'system_generated' => true],
            'recipients_count' => 1,
            'sent_count' => 1,
            'failed_count' => 0,
            'status' => 'completed',
            'sent_at' => now(),
            'created_by' => $createdBy ?? auth('admin')->id(),
            'is_sensitive' => $isSensitive,
            'metadata' => [
                'system_message' => true,
                'customer_id' => $customer->id,
            ],
        ]);
    }

    /**
     * Send a message to a single customer.
     */
    public function sendToCustomer(Request $request, Customer $customer): RedirectResponse
    {
        abort_unless(auth('admin')->user()?->can('customers.send-message'), 403);
        
        $admin = auth('admin')->user();
        $companyFilterId = $admin->getCompanyFilterId();

        // Ensure non-primary admins can only send messages to customers from their company
        if ($companyFilterId !== null && $customer->company_id != $companyFilterId) {
            abort(403, 'You can only send messages to customers from your company.');
        }

        $validated = $request->validate([
            'subject' => ['required_if:type,email,both', 'nullable', 'string', 'max:255'],
            'message' => ['required', 'string', 'max:5000'],
            'type' => ['required', 'in:sms,email,both'],
        ]);

        try {
            DB::beginTransaction();

            $subject = $this->resolveSubject($validated['type'], $validated['subject'] ?? null, $customer);

            // Create communication record
            $communication = Communication::create([
                'subject' => $subject,
                'message' => $validated['message'],
                'type' => $validated['type'],
                'filters' => ['customer_id' => $customer->id], // Store single customer ID in filters
                'recipients_count' => 1,
                'status' => 'sending',
                'created_by' => auth('admin')->id(),
            ]);

            // Send communications
            $sentCount = 0;
            $failedCount = 0;
            $errors = [];
            $smsMessageIds = [];

            try {
                if (in_array($validated['type'], ['email', 'both'])) {
                    if ($customer->email) {
                        $this->sendEmail($customer, $subject, $validated['message']);
                        $sentCount++;
                    } else {
                        $failedCount++;
                        $errors[] = "Customer has no email address";
                    }
                }

                if (in_array($validated['type'], ['sms', 'both'])) {
                    if ($customer->phone) {
                        $sms = $this->sendSms($customer, $validated['message'], $communication->id);
                        $smsMessageIds[] = $sms->id;
                        if ($validated['type'] === 'sms') {
                            $sentCount++;
                        }
                    } else {
                        $failedCount++;
                        $errors[] = "Customer has no phone number";
                    }
                }
            } catch (\Exception $e) {
                $failedCount++;
                $errors[] = "Failed to send: " . $e->getMessage();
                Log::error('Single customer communication error', [
                    'communication_id' => $communication->id,
                    'customer_id' => $customer->id,
                    'error' => $e->getMessage(),
                ]);
            }

            // Update communication status from real delivery outcome
            $communication->update([
                'sent_count' => $sentCount,
                'failed_count' => $failedCount,
                'status' => $failedCount > 0 && $sentCount === 0 ? 'failed' : 'completed',
                'sent_at' => now(),
                'error_message' => !empty($errors) ? implode('; ', $errors) : null,
                'metadata' => array_filter([
                    'sms_message_ids' => $smsMessageIds ?: null,
                ]),
            ]);

            DB::commit();

            if ($sentCount > 0) {
                $channelLabel = match ($validated['type']) {
                    'sms' => 'SMS',
                    'email' => 'Email',
                    default => 'Message',
                };

                $message = $validated['type'] === 'sms'
                    ? "{$channelLabel} was accepted by the provider for {$customer->full_name}."
                    : "{$channelLabel} has been sent to {$customer->full_name}.";

                return redirect()->route('admin.customers.show', $customer)
                    ->with('success', $message)
                    ->with('message_queued', false);
            }

            return redirect()->route('admin.customers.show', $customer)
                ->with('error', 'Failed to send message. '.($errors[0] ?? 'Unknown error'));


        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Single customer communication creation error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return redirect()->back()
                ->withInput()
                ->with('error', 'Failed to send message: ' . $e->getMessage());
        }
    }
}
