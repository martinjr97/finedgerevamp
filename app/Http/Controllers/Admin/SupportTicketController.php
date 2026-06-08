<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Admin;
use App\Models\Customer;
use App\Models\SupportTicket;
use App\Models\SupportTicketAttachment;
use App\Services\SupportTicketService;
use App\Support\DocumentUploadRules;
use App\Support\ZambianPhoneRules;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SupportTicketController extends Controller
{
    public function __construct(
        protected SupportTicketService $supportTicketService
    ) {}

    public function index(Request $request): View
    {
        $admin = auth('admin')->user();
        $this->authorize('viewAny', SupportTicket::class);

        $query = SupportTicket::query()
            ->with(['customer', 'assignedTo', 'handler'])
            ->withMax('comments', 'created_at');

        $companyFilterId = $admin->getCompanyFilterId();
        if ($companyFilterId !== null) {
            $query->where(function ($q) use ($companyFilterId, $admin) {
                $q->whereHas('customer', fn ($customerQuery) => $customerQuery->where('company_id', $companyFilterId))
                    ->orWhere('assigned_to_id', $admin->id);
            });
        }

        if ($request->filled('status')) {
            $query->where('status', $request->string('status')->toString());
        }

        if ($request->string('assignment')->toString() === 'me') {
            $query->where('assigned_to_id', $admin->id);
        } elseif ($request->string('assignment')->toString() === 'unassigned') {
            $query->whereNull('assigned_to_id');
        }

        $sort = $request->string('sort')->toString();
        if ($sort === 'oldest') {
            $query->oldest();
        } else {
            $query->latest();
        }

        $tickets = $query->paginate(20)->withQueryString();

        $staffMembers = Admin::query()
            ->where('is_active', true)
            ->when($companyFilterId !== null, fn ($q) => $q->where('company_id', $companyFilterId))
            ->orderBy('first_name')
            ->orderBy('last_name')
            ->get(['id', 'first_name', 'last_name', 'email']);

        return view('admin.support-tickets.index', compact('tickets', 'staffMembers'));
    }

    public function create(): View
    {
        $this->authorize('create', SupportTicket::class);

        $admin = auth('admin')->user();
        $companyFilterId = $admin->getCompanyFilterId();

        $customers = Customer::query()
            ->when($companyFilterId !== null, fn ($q) => $q->where('company_id', $companyFilterId))
            ->orderBy('first_name')
            ->orderBy('last_name')
            ->limit(300)
            ->get(['id', 'first_name', 'last_name', 'email', 'phone']);

        $staffMembers = Admin::query()
            ->where('is_active', true)
            ->when($companyFilterId !== null, fn ($q) => $q->where('company_id', $companyFilterId))
            ->orderBy('first_name')
            ->orderBy('last_name')
            ->get(['id', 'first_name', 'last_name', 'email']);

        return view('admin.support-tickets.create', compact('customers', 'staffMembers'));
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create', SupportTicket::class);

        $admin = auth('admin')->user();
        $companyFilterId = $admin->getCompanyFilterId();

        $data = $request->validate([
            'customer_id' => ['nullable', 'integer', 'exists:customers,id'],
            'name' => ['required_without:customer_id', 'nullable', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ZambianPhoneRules::nullable(),
            'subject' => ['required', 'string', 'max:255'],
            'message' => ['required', 'string', 'max:5000'],
            'assigned_to_id' => ['nullable', 'integer', 'exists:admins,id'],
            'assignment_note' => ['nullable', 'string', 'max:1000'],
            'attachment' => DocumentUploadRules::nullableSupportAttachment(),
        ]);

        if (! empty($data['customer_id']) && $companyFilterId !== null) {
            $belongsToCompany = Customer::query()
                ->where('id', $data['customer_id'])
                ->where('company_id', $companyFilterId)
                ->exists();

            if (! $belongsToCompany) {
                return redirect()
                    ->back()
                    ->withErrors(['customer_id' => 'The selected customer is not available for your company.'])
                    ->withInput();
            }
        }

        if (! empty($data['customer_id'])) {
            $customer = Customer::query()->findOrFail($data['customer_id']);
            $data['name'] = $data['name'] ?? $customer->full_name;
            $data['email'] = $data['email'] ?? $customer->email;
            $data['phone'] = $data['phone'] ?? $customer->phone;
        }

        $ticket = $this->supportTicketService->createTicket($data, $admin);

        if ($request->hasFile('attachment')) {
            $this->supportTicketService->storeAttachment(
                $ticket,
                $request->file('attachment'),
                SupportTicketAttachment::UPLOADER_ADMIN,
                admin: $admin,
                visibleToCustomer: true
            );
        }

        return redirect()
            ->route('admin.support-tickets.show', $ticket)
            ->with('status', 'Support ticket #'.$ticket->id.' created successfully.');
    }

    public function show(SupportTicket $supportTicket): View
    {
        $this->authorize('view', $supportTicket);

        $admin = auth('admin')->user();

        if (! $supportTicket->viewed_at) {
            $supportTicket->viewed_at = now();
            if ($supportTicket->status === SupportTicket::STATUS_NEW) {
                $supportTicket->status = SupportTicket::STATUS_IN_PROGRESS;
            }
            $supportTicket->save();
        }

        $supportTicket->load([
            'customer',
            'assignedTo',
            'assignedBy',
            'handler',
            'comments.customer',
            'comments.admin',
            'assignments.assignedTo',
            'assignments.assignedBy',
            'assignments.previousAssignedTo',
            'attachments.admin',
            'attachments.customer',
        ]);

        $companyFilterId = $admin->getCompanyFilterId();
        $staffMembers = Admin::query()
            ->where('is_active', true)
            ->when($companyFilterId !== null, fn ($q) => $q->where('company_id', $companyFilterId))
            ->orderBy('first_name')
            ->orderBy('last_name')
            ->get(['id', 'first_name', 'last_name', 'email']);

        return view('admin.support-tickets.show', [
            'ticket' => $supportTicket,
            'statuses' => SupportTicket::statuses(),
            'staffMembers' => $staffMembers,
            'canAssign' => $admin->can('assign', $supportTicket),
            'canComment' => $admin->can('comment', $supportTicket),
            'canUpdateStatus' => $admin->can('updateStatus', $supportTicket),
        ]);
    }

    public function assign(Request $request, SupportTicket $supportTicket): RedirectResponse
    {
        $this->authorize('assign', $supportTicket);

        $data = $request->validate([
            'assigned_to_id' => ['nullable', 'integer', 'exists:admins,id'],
            'note' => ['nullable', 'string', 'max:1000'],
        ]);

        $assignedTo = isset($data['assigned_to_id'])
            ? Admin::query()->findOrFail($data['assigned_to_id'])
            : null;

        $this->supportTicketService->assignTicket(
            $supportTicket,
            $assignedTo,
            auth('admin')->user(),
            $data['note'] ?? null
        );

        return redirect()
            ->route('admin.support-tickets.show', $supportTicket)
            ->with('status', 'Ticket assignment updated successfully.');
    }

    public function storeComment(Request $request, SupportTicket $supportTicket): RedirectResponse
    {
        $this->authorize('comment', $supportTicket);

        $data = $request->validate([
            'comment' => ['required', 'string', 'max:5000'],
            'is_internal' => ['sometimes', 'boolean'],
        ]);

        $isInternal = $request->boolean('is_internal');

        try {
            $this->supportTicketService->addAdminComment(
                $supportTicket,
                auth('admin')->user(),
                $data['comment'],
                $isInternal
            );
        } catch (\InvalidArgumentException $e) {
            return redirect()
                ->back()
                ->withErrors(['comment' => $e->getMessage()])
                ->withInput();
        }

        return redirect()
            ->route('admin.support-tickets.show', $supportTicket)
            ->with('status', $isInternal ? 'Internal note added.' : 'Reply posted and visible to the customer.');
    }

    public function updateStatus(Request $request, SupportTicket $supportTicket): RedirectResponse
    {
        $this->authorize('updateStatus', $supportTicket);

        $data = $request->validate([
            'status' => ['required', Rule::in(SupportTicket::statuses())],
            'resolution_note' => ['nullable', 'string', 'max:2000'],
            'comment' => ['nullable', 'string', 'max:5000'],
        ]);

        if ($data['status'] === SupportTicket::STATUS_RESOLVED && blank($data['resolution_note'] ?? null)) {
            return redirect()
                ->back()
                ->withErrors(['resolution_note' => 'Please provide resolution details before marking this ticket as resolved.'])
                ->withInput();
        }

        try {
            $this->supportTicketService->changeStatus(
                $supportTicket,
                $data['status'],
                auth('admin')->user(),
                $data['comment'] ?? null,
                $data['resolution_note'] ?? null
            );
        } catch (\InvalidArgumentException $e) {
            return redirect()
                ->back()
                ->withErrors(['comment' => $e->getMessage()])
                ->withInput();
        }

        return redirect()
            ->route('admin.support-tickets.show', $supportTicket)
            ->with('status', 'Support ticket status updated successfully.');
    }

    public function downloadAttachment(
        SupportTicket $supportTicket,
        SupportTicketAttachment $attachment
    ): StreamedResponse {
        $this->authorize('view', $supportTicket);
        abort_unless((int) $attachment->support_ticket_id === (int) $supportTicket->id, 404);
        abort_unless(Storage::disk('public')->exists($attachment->path), 404);

        return Storage::disk('public')->download(
            $attachment->path,
            $attachment->original_name,
            ['Content-Type' => $attachment->mime_type ?? 'application/octet-stream']
        );
    }

    /** @deprecated Use updateStatus — kept for backward compatibility */
    public function update(Request $request, SupportTicket $supportTicket): RedirectResponse
    {
        $request->merge([
            'comment' => null,
        ]);

        return $this->updateStatus($request, $supportTicket);
    }
}
