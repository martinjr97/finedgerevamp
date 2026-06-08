<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Support\DocumentUploadRules;
use App\Models\Admin;
use App\Models\Company;
use App\Models\Customer;
use App\Models\CustomerGroup;
use App\Models\GroupLoanApplication;
use App\Models\GroupLoanApplicationDocument;
use App\Models\GroupLoanApplicationMember;
use App\Models\GroupMemberTitle;
use App\Models\Loan;
use App\Models\LoanProduct;
use App\Services\CustomerNotificationService;
use App\Services\GroupLoanCalculationService;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\Response;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class GroupLoanApplicationController extends Controller
{
    private const ASSIGN_RELATIONSHIP_MANAGER_PERMISSION = 'can assign relationship manager to group';

    public function __construct(
        private readonly GroupLoanCalculationService $calculationService,
        private readonly CustomerNotificationService $customerNotificationService,
    ) {
    }

    public function index(Request $request): View
    {
        abort_unless(auth('admin')->user()?->can('loans.view'), 403);

        $query = GroupLoanApplication::query()
            ->with(['loanProduct', 'customerGroup', 'relationshipManager', 'creator', 'approver'])
            ->withCount('members');

        if ($request->filled('status')) {
            $query->where('status', $request->string('status'));
        }

        if ($request->filled('customer_group_id')) {
            $query->where('customer_group_id', (int) $request->integer('customer_group_id'));
        }

        if ($request->filled('search')) {
            $search = trim((string) $request->string('search'));
            $query->where(function ($builder) use ($search): void {
                $builder->where('reference', 'like', "%{$search}%")
                    ->orWhere('loan_name', 'like', "%{$search}%")
                    ->orWhere('group_name', 'like', "%{$search}%");
            });
        }

        $applications = $query->latest('id')->paginate(20)->withQueryString();

        $groupProductIds = LoanProduct::query()->where('category', 'group_loans')->pluck('id');
        $groups = CustomerGroup::query()
            ->whereIn('loan_product_id', $groupProductIds)
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        return view('admin.loan-applications.group-loans.index', [
            'applications' => $applications,
            'groups' => $groups,
        ]);
    }

    public function members(Request $request, LoanProduct $loanProduct): View
    {
        abort_unless(auth('admin')->user()?->can('loans.create'), 403);
        $this->ensureGroupLoanProduct($loanProduct);

        $wizard = $this->wizard($request, $loanProduct);
        $selectedGroupId = (int) ($request->query('customer_group_id')
            ?: ($wizard['customer_group_id'] ?? 0));

        $groups = CustomerGroup::query()
            ->where('loan_product_id', $loanProduct->id)
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        $customers = collect();
        if ($selectedGroupId > 0) {
            $customers = Customer::query()
                ->where('loan_product_id', $loanProduct->id)
                ->where('customer_group_id', $selectedGroupId)
                ->where('status', 'active')
                ->where('approval_status', 'approved')
                ->orderBy('first_name')
                ->orderBy('last_name')
                ->get();
        }

        $titles = GroupMemberTitle::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        return view('admin.loan-applications.group-loans.members', [
            'loanProduct' => $loanProduct,
            'groups' => $groups,
            'customers' => $customers,
            'titles' => $titles,
            'selectedGroupId' => $selectedGroupId,
            'wizard' => $wizard,
        ]);
    }

    public function storeMembers(Request $request, LoanProduct $loanProduct): RedirectResponse
    {
        abort_unless(auth('admin')->user()?->can('loans.create'), 403);
        $this->ensureGroupLoanProduct($loanProduct);

        $validated = $request->validate([
            'customer_group_id' => ['required', 'integer', 'exists:customer_groups,id'],
            'member_ids' => ['required', 'array', 'min:3', 'max:10'],
            'member_ids.*' => ['integer', 'distinct', 'exists:customers,id'],
            'member_titles' => ['required', 'array'],
            'member_titles.*' => ['nullable', 'integer', 'exists:group_member_titles,id'],
        ], [
            'member_ids.min' => 'A group loan application must include at least 3 members.',
            'member_ids.max' => 'A group loan application can include a maximum of 10 members.',
        ]);

        $group = CustomerGroup::query()
            ->where('id', $validated['customer_group_id'])
            ->where('loan_product_id', $loanProduct->id)
            ->where('is_active', true)
            ->first();

        if (! $group) {
            throw ValidationException::withMessages([
                'customer_group_id' => 'The selected group does not belong to the Group Loans product.',
            ]);
        }

        $memberIds = collect($validated['member_ids'])->map(fn ($id) => (int) $id)->values();

        $eligibleMembers = Customer::query()
            ->whereIn('id', $memberIds)
            ->where('loan_product_id', $loanProduct->id)
            ->where('customer_group_id', $group->id)
            ->where('status', 'active')
            ->where('approval_status', 'approved')
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->values();

        if ($eligibleMembers->count() !== $memberIds->count()) {
            throw ValidationException::withMessages([
                'member_ids' => 'Some selected customers are not eligible for this group loan application.',
            ]);
        }

        $memberTitles = [];
        foreach ($memberIds as $customerId) {
            $titleId = (int) ($validated['member_titles'][$customerId] ?? 0);
            if ($titleId <= 0) {
                throw ValidationException::withMessages([
                    "member_titles.{$customerId}" => 'Every selected member must have a group title.',
                ]);
            }

            $memberTitles[$customerId] = $titleId;
        }

        $titleNames = GroupMemberTitle::query()
            ->whereIn('id', array_values($memberTitles))
            ->pluck('name')
            ->map(fn ($name) => Str::lower(trim((string) $name)));

        if (! $titleNames->intersect(['leader', 'coordinator'])->isNotEmpty()) {
            throw ValidationException::withMessages([
                'member_titles' => 'At least one selected member must be a Leader or Coordinator.',
            ]);
        }

        $wizard = $this->wizard($request, $loanProduct);
        $wizard['customer_group_id'] = $group->id;
        $wizard['member_ids'] = $memberIds->all();
        $wizard['member_titles'] = $memberTitles;
        $this->storeWizard($request, $loanProduct, $wizard);

        return redirect()
            ->route('admin.loan-applications.group-loans.details', $loanProduct)
            ->with('status', 'Members selected successfully.');
    }

    public function details(Request $request, LoanProduct $loanProduct): View|RedirectResponse
    {
        abort_unless(auth('admin')->user()?->can('loans.create'), 403);
        $this->ensureGroupLoanProduct($loanProduct);
        /** @var Admin $admin */
        $admin = auth('admin')->user();

        $wizard = $this->wizard($request, $loanProduct);
        if (empty($wizard['member_ids']) || empty($wizard['customer_group_id'])) {
            return redirect()
                ->route('admin.loan-applications.group-loans.members', $loanProduct)
                ->with('error', 'Please select members first.');
        }

        $group = CustomerGroup::query()->findOrFail((int) $wizard['customer_group_id']);
        $members = Customer::query()->whereIn('id', $wizard['member_ids'])->get();
        $canAssignRelationshipManager = $admin->can(self::ASSIGN_RELATIONSHIP_MANAGER_PERMISSION);
        $canProceedWithRelationshipManager = $canAssignRelationshipManager || $admin->is_relationship_manager;

        $relationshipManagers = collect();
        if ($canAssignRelationshipManager) {
            $relationshipManagers = Admin::query()
                ->where('is_relationship_manager', true)
                ->where('is_active', true)
                ->orderBy('first_name')
                ->orderBy('last_name')
                ->get();
        }

        $selectedRelationshipManagerId = (int) (
            $wizard['relationship_manager_id']
            ?? ($canAssignRelationshipManager ? 0 : $admin->id)
        );

        return view('admin.loan-applications.group-loans.details', [
            'loanProduct' => $loanProduct,
            'wizard' => $wizard,
            'group' => $group,
            'members' => $members,
            'relationshipManagers' => $relationshipManagers,
            'canAssignRelationshipManager' => $canAssignRelationshipManager,
            'canProceedWithRelationshipManager' => $canProceedWithRelationshipManager,
            'selectedRelationshipManagerId' => $selectedRelationshipManagerId,
            'currentAdmin' => $admin,
        ]);
    }

    public function storeDetails(Request $request, LoanProduct $loanProduct): RedirectResponse
    {
        abort_unless(auth('admin')->user()?->can('loans.create'), 403);
        $this->ensureGroupLoanProduct($loanProduct);
        /** @var Admin $admin */
        $admin = auth('admin')->user();
        $canAssignRelationshipManager = $admin->can(self::ASSIGN_RELATIONSHIP_MANAGER_PERMISSION);

        $wizard = $this->wizard($request, $loanProduct);
        if (empty($wizard['member_ids']) || empty($wizard['customer_group_id'])) {
            return redirect()
                ->route('admin.loan-applications.group-loans.members', $loanProduct)
                ->with('error', 'Please select members first.');
        }

        $validated = $request->validate([
            'loan_name' => ['required', 'string', 'max:255'],
            'terms_and_conditions' => ['nullable', 'string', 'max:5000'],
            'start_date' => ['required', 'date'],
            'loan_term_value' => ['nullable', 'integer', 'min:1', 'required_with:loan_term_unit'],
            'loan_term_unit' => ['nullable', 'in:weeks,months', 'required_with:loan_term_value'],
            'due_date' => ['nullable', 'date', 'after:start_date'],
            'repayment_structure' => ['required', 'in:weekly,monthly'],
            'processing_fee_percentage' => ['required', 'numeric', 'min:0'],
            'monthly_interest_rate' => ['required', 'numeric', 'min:0'],
            'arrears_rate' => ['required', 'numeric', 'min:0'],
            'relationship_manager_id' => ['nullable', 'integer', 'exists:admins,id'],
        ]);

        $loanTermValue = isset($validated['loan_term_value']) ? (int) $validated['loan_term_value'] : null;
        $loanTermUnit = $validated['loan_term_unit'] ?? null;
        $dueDate = $validated['due_date'] ?? null;

        if ($loanTermValue && $loanTermUnit) {
            $dueDate = $this->computeDueDateFromLoanTerm(
                (string) $validated['start_date'],
                $loanTermValue,
                (string) $loanTermUnit
            );
        }

        if (! $dueDate) {
            throw ValidationException::withMessages([
                'due_date' => 'Please provide a due date or set a loan term value and unit to auto-calculate it.',
            ]);
        }
        $relationshipManagerId = $this->resolveRelationshipManagerId(
            $admin,
            $canAssignRelationshipManager,
            isset($validated['relationship_manager_id']) ? (int) $validated['relationship_manager_id'] : null
        );

        $wizard['loan_name'] = $validated['loan_name'];
        $wizard['terms_and_conditions'] = $validated['terms_and_conditions'] ?? null;
        $wizard['start_date'] = $validated['start_date'];
        $wizard['loan_term_value'] = $loanTermValue;
        $wizard['loan_term_unit'] = $loanTermUnit;
        unset($wizard['loan_term_option']);
        $wizard['due_date'] = $dueDate;
        $wizard['repayment_structure'] = $validated['repayment_structure'];
        $wizard['processing_fee_percentage'] = (float) $validated['processing_fee_percentage'];
        $wizard['monthly_interest_rate'] = (float) $validated['monthly_interest_rate'];
        $wizard['arrears_rate'] = (float) $validated['arrears_rate'];
        $wizard['relationship_manager_id'] = $relationshipManagerId;

        $this->storeWizard($request, $loanProduct, $wizard);

        return redirect()->route('admin.loan-applications.group-loans.principals', $loanProduct);
    }

    public function principals(Request $request, LoanProduct $loanProduct): View|RedirectResponse
    {
        abort_unless(auth('admin')->user()?->can('loans.create'), 403);
        $this->ensureGroupLoanProduct($loanProduct);

        $wizard = $this->wizard($request, $loanProduct);
        if (empty($wizard['loan_name']) || empty($wizard['member_ids'])) {
            return redirect()
                ->route('admin.loan-applications.group-loans.details', $loanProduct)
                ->with('error', 'Please complete the group loan details first.');
        }

        $members = Customer::query()
            ->whereIn('id', $wizard['member_ids'])
            ->with('groupMemberTitle')
            ->get()
            ->keyBy('id');

        $titles = GroupMemberTitle::query()->whereIn('id', array_values($wizard['member_titles'] ?? []))->get()->keyBy('id');

        return view('admin.loan-applications.group-loans.principals', [
            'loanProduct' => $loanProduct,
            'wizard' => $wizard,
            'members' => $members,
            'titles' => $titles,
        ]);
    }

    public function storePrincipals(Request $request, LoanProduct $loanProduct): RedirectResponse
    {
        abort_unless(auth('admin')->user()?->can('loans.create'), 403);
        $this->ensureGroupLoanProduct($loanProduct);

        $wizard = $this->wizard($request, $loanProduct);
        if (empty($wizard['loan_name']) || empty($wizard['member_ids'])) {
            return redirect()
                ->route('admin.loan-applications.group-loans.details', $loanProduct)
                ->with('error', 'Please complete the group loan details first.');
        }

        $validated = $request->validate([
            'principals' => ['required', 'array'],
            'principals.*' => ['required', 'numeric', 'gt:0'],
        ]);

        $memberIds = collect($wizard['member_ids'])->map(fn ($id) => (int) $id)->values();
        $principalInput = collect($validated['principals'])
            ->mapWithKeys(fn ($amount, $customerId) => [(int) $customerId => (float) $amount]);

        foreach ($memberIds as $memberId) {
            if (! $principalInput->has($memberId)) {
                throw ValidationException::withMessages([
                    'principals' => 'Every selected member must have a principal amount.',
                ]);
            }
        }

        $calculation = $this->calculationService->calculate([
            'processing_fee_percentage' => $wizard['processing_fee_percentage'] ?? null,
            'monthly_interest_rate' => $wizard['monthly_interest_rate'] ?? null,
            'arrears_rate' => $wizard['arrears_rate'] ?? null,
            'repayment_structure' => $wizard['repayment_structure'] ?? null,
            'start_date' => $wizard['start_date'] ?? null,
            'due_date' => $wizard['due_date'] ?? null,
            'principals' => $memberIds
                ->mapWithKeys(fn ($memberId) => [$memberId => (float) $principalInput[$memberId]])
                ->all(),
        ]);

        $wizard['principals'] = $memberIds
            ->mapWithKeys(fn ($memberId) => [$memberId => (float) $principalInput[$memberId]])
            ->all();

        $wizard['member_calculations'] = collect($calculation['members'])
            ->mapWithKeys(fn ($member) => [(int) $member['customer_id'] => $member])
            ->all();
        $wizard['totals'] = $calculation['totals'];
        $wizard['installment_count'] = $calculation['installment_count'];
        $wizard['duration_days'] = $calculation['duration_days'];

        $this->storeWizard($request, $loanProduct, $wizard);

        return redirect()->route('admin.loan-applications.group-loans.documents', $loanProduct);
    }

    public function documents(Request $request, LoanProduct $loanProduct): View|RedirectResponse
    {
        abort_unless(auth('admin')->user()?->can('loans.create'), 403);
        $this->ensureGroupLoanProduct($loanProduct);

        $wizard = $this->wizard($request, $loanProduct);
        if (empty($wizard['member_calculations'])) {
            return redirect()
                ->route('admin.loan-applications.group-loans.principals', $loanProduct)
                ->with('error', 'Please complete principal entry first.');
        }

        return view('admin.loan-applications.group-loans.documents', [
            'loanProduct' => $loanProduct,
            'wizard' => $wizard,
            'documents' => $wizard['documents'] ?? [],
        ]);
    }

    public function storeDocuments(Request $request, LoanProduct $loanProduct): RedirectResponse
    {
        abort_unless(auth('admin')->user()?->can('loans.create'), 403);
        $this->ensureGroupLoanProduct($loanProduct);

        $wizard = $this->wizard($request, $loanProduct);
        if (empty($wizard['member_calculations'])) {
            return redirect()
                ->route('admin.loan-applications.group-loans.principals', $loanProduct)
                ->with('error', 'Please complete principal entry first.');
        }

        if ($request->input('action') === 'continue') {
            return redirect()->route('admin.loan-applications.group-loans.review', $loanProduct);
        }

        $validated = $request->validate([
            'document_name' => ['required', 'string', 'max:255'],
            'document_file' => DocumentUploadRules::groupLoanDocumentRule(),
            'description' => ['nullable', 'string', 'max:1000'],
        ]);

        $path = $request->file('document_file')->store('group-loan-documents/supporting', 'public');

        $documents = $wizard['documents'] ?? [];
        $documents[] = [
            'document_name' => $validated['document_name'],
            'file_path' => $path,
            'description' => $validated['description'] ?? null,
        ];

        $wizard['documents'] = $documents;
        $this->storeWizard($request, $loanProduct, $wizard);

        return redirect()
            ->route('admin.loan-applications.group-loans.documents', $loanProduct)
            ->with('status', 'Supporting document added.');
    }

    public function review(Request $request, LoanProduct $loanProduct): View|RedirectResponse
    {
        abort_unless(auth('admin')->user()?->can('loans.create'), 403);
        $this->ensureGroupLoanProduct($loanProduct);
        /** @var Admin $admin */
        $admin = auth('admin')->user();

        $wizard = $this->wizard($request, $loanProduct);

        if (empty($wizard['member_calculations']) || empty($wizard['totals'])) {
            return redirect()
                ->route('admin.loan-applications.group-loans.principals', $loanProduct)
                ->with('error', 'Please complete principal entry first.');
        }

        $members = Customer::query()
            ->whereIn('id', $wizard['member_ids'] ?? [])
            ->with(['customerGroup', 'groupMemberTitle'])
            ->get()
            ->keyBy('id');

        $titles = GroupMemberTitle::query()
            ->whereIn('id', array_values($wizard['member_titles'] ?? []))
            ->get()
            ->keyBy('id');

        $group = CustomerGroup::query()->find((int) ($wizard['customer_group_id'] ?? 0));
        $relationshipManager = Admin::query()->find((int) ($wizard['relationship_manager_id'] ?? 0));
        $canAssignRelationshipManager = $admin->can(self::ASSIGN_RELATIONSHIP_MANAGER_PERMISSION);
        $relationshipManagers = collect();
        if ($canAssignRelationshipManager) {
            $relationshipManagers = Admin::query()
                ->where('is_relationship_manager', true)
                ->where('is_active', true)
                ->orderBy('first_name')
                ->orderBy('last_name')
                ->get();
        }
        $installmentCount = (int) ($wizard['installment_count'] ?? 0);
        $memberInstallmentSchedules = $this->buildMemberInstallmentSchedules(
            (array) ($wizard['member_calculations'] ?? []),
            $installmentCount
        );
        $repaymentSchedule = $this->buildRepaymentSchedule(
            (string) ($wizard['start_date'] ?? ''),
            (string) ($wizard['due_date'] ?? ''),
            (string) ($wizard['repayment_structure'] ?? ''),
            (float) data_get($wizard, 'totals.repayment_amount', 0),
            $installmentCount
        );
        $repaymentSchedule = $this->appendMemberBreakdownToSchedule($repaymentSchedule, $memberInstallmentSchedules);
        if ($installmentCount <= 0) {
            $installmentCount = count($repaymentSchedule);
        }

        $revisionSourceApplication = null;
        $isModificationRevision = false;
        $canAddModificationNote = false;
        $revisionRejectionActionRequired = '';
        $revisionReviewerNotes = '';

        $revisionSourceApplicationId = (int) ($wizard['revision_source_application_id'] ?? 0);
        if ($revisionSourceApplicationId > 0) {
            $revisionSourceApplication = GroupLoanApplication::query()->find($revisionSourceApplicationId);

            if ($revisionSourceApplication) {
                $sourceMetadata = $this->applicationMetadata($revisionSourceApplication);
                $isModificationRevision = $revisionSourceApplication->status === 'rejected'
                    && (string) data_get($sourceMetadata, 'rejection.resolution') === 'changes_requested';

                if ($isModificationRevision) {
                    $canAddModificationNote = $this->canAddModificationNote($admin, $revisionSourceApplication);
                    $revisionRejectionActionRequired = (string) data_get($sourceMetadata, 'rejection.action_required', '');
                    $revisionReviewerNotes = (string) ($revisionSourceApplication->approval_notes ?? '');
                }
            }
        }

        return view('admin.loan-applications.group-loans.review', [
            'loanProduct' => $loanProduct,
            'wizard' => $wizard,
            'members' => $members,
            'titles' => $titles,
            'group' => $group,
            'relationshipManager' => $relationshipManager,
            'canAssignRelationshipManager' => $canAssignRelationshipManager,
            'relationshipManagers' => $relationshipManagers,
            'repaymentSchedule' => $repaymentSchedule,
            'memberInstallmentSchedules' => $memberInstallmentSchedules,
            'installmentCount' => $installmentCount,
            'revisionSourceApplication' => $revisionSourceApplication,
            'isModificationRevision' => $isModificationRevision,
            'canAddModificationNote' => $canAddModificationNote,
            'revisionRejectionActionRequired' => $revisionRejectionActionRequired,
            'revisionReviewerNotes' => $revisionReviewerNotes,
        ]);
    }

    public function updateReviewRelationshipManager(Request $request, LoanProduct $loanProduct): RedirectResponse
    {
        abort_unless(auth('admin')->user()?->can('loans.create'), 403);
        $this->ensureGroupLoanProduct($loanProduct);
        /** @var Admin $admin */
        $admin = auth('admin')->user();

        $wizard = $this->wizard($request, $loanProduct);
        if (empty($wizard['member_ids']) || empty($wizard['customer_group_id'])) {
            return redirect()
                ->route('admin.loan-applications.group-loans.members', $loanProduct)
                ->with('error', 'Please select members first.');
        }

        $validated = $request->validate([
            'relationship_manager_id' => ['nullable', 'integer', 'exists:admins,id'],
        ]);

        $relationshipManagerId = $this->resolveRelationshipManagerId(
            $admin,
            $admin->can(self::ASSIGN_RELATIONSHIP_MANAGER_PERMISSION),
            isset($validated['relationship_manager_id']) ? (int) $validated['relationship_manager_id'] : null
        );

        $wizard['relationship_manager_id'] = $relationshipManagerId;
        $this->storeWizard($request, $loanProduct, $wizard);

        return redirect()
            ->route('admin.loan-applications.group-loans.review', $loanProduct)
            ->with('status', 'Relationship manager updated for this draft group loan application.');
    }

    public function reviewPrint(Request $request, LoanProduct $loanProduct): Response|RedirectResponse
    {
        abort_unless(auth('admin')->user()?->can('loans.create'), 403);
        $this->ensureGroupLoanProduct($loanProduct);

        $wizard = $this->wizard($request, $loanProduct);

        if (empty($wizard['member_calculations']) || empty($wizard['totals'])) {
            return redirect()
                ->route('admin.loan-applications.group-loans.principals', $loanProduct)
                ->with('error', 'Please complete principal entry first.');
        }

        $members = Customer::query()
            ->whereIn('id', $wizard['member_ids'] ?? [])
            ->with(['customerGroup', 'groupMemberTitle'])
            ->get()
            ->keyBy('id');

        $titles = GroupMemberTitle::query()
            ->whereIn('id', array_values($wizard['member_titles'] ?? []))
            ->get()
            ->keyBy('id');

        $group = CustomerGroup::query()->find((int) ($wizard['customer_group_id'] ?? 0));
        $relationshipManager = Admin::query()->find((int) ($wizard['relationship_manager_id'] ?? 0));
        $installmentCount = (int) ($wizard['installment_count'] ?? 0);
        $memberInstallmentSchedules = $this->buildMemberInstallmentSchedules(
            (array) ($wizard['member_calculations'] ?? []),
            $installmentCount
        );
        $repaymentSchedule = $this->buildRepaymentSchedule(
            (string) ($wizard['start_date'] ?? ''),
            (string) ($wizard['due_date'] ?? ''),
            (string) ($wizard['repayment_structure'] ?? ''),
            (float) data_get($wizard, 'totals.repayment_amount', 0),
            $installmentCount
        );
        $repaymentSchedule = $this->appendMemberBreakdownToSchedule($repaymentSchedule, $memberInstallmentSchedules);
        if ($installmentCount <= 0) {
            $installmentCount = count($repaymentSchedule);
        }

        $generatedAt = now();
        $pdf = Pdf::loadView('admin.loan-applications.group-loans.review-print', [
            'loanProduct' => $loanProduct,
            'wizard' => $wizard,
            'members' => $members,
            'titles' => $titles,
            'group' => $group,
            'relationshipManager' => $relationshipManager,
            'repaymentSchedule' => $repaymentSchedule,
            'memberInstallmentSchedules' => $memberInstallmentSchedules,
            'installmentCount' => $installmentCount,
            'printedBy' => auth('admin')->user(),
            'printBranding' => $this->buildPrintBranding(),
            'generatedAt' => $generatedAt,
        ])->setPaper('a4', 'portrait');

        $loanName = trim((string) ($wizard['loan_name'] ?? 'group-loan-review'));
        $slug = Str::slug($loanName);
        $filename = 'group-loan-review-'.($slug !== '' ? $slug : 'draft').'-'.$generatedAt->format('Ymd_His').'.pdf';

        return $pdf->download($filename);
    }

    public function submit(Request $request, LoanProduct $loanProduct): RedirectResponse
    {
        abort_unless(auth('admin')->user()?->can('loans.create'), 403);
        $this->ensureGroupLoanProduct($loanProduct);
        /** @var Admin $admin */
        $admin = auth('admin')->user();

        $wizard = $this->wizard($request, $loanProduct);

        if (
            empty($wizard['member_calculations'])
            || empty($wizard['totals'])
            || empty($wizard['member_ids'])
            || empty($wizard['customer_group_id'])
            || empty($wizard['loan_name'])
            || empty($wizard['relationship_manager_id'])
        ) {
            return redirect()
                ->route('admin.loan-applications.group-loans.members', $loanProduct)
                ->with('error', 'The group loan application is incomplete.');
        }

        $sourceApplication = null;
        $sourceApplicationId = (int) ($wizard['revision_source_application_id'] ?? 0);
        if ($sourceApplicationId > 0) {
            $sourceApplication = GroupLoanApplication::query()
                ->with(['members.groupMemberTitle', 'documents'])
                ->find($sourceApplicationId);
        }

        if ($sourceApplication) {
            if (! $this->canCreateRevisionDraft($admin, $sourceApplication)) {
                return redirect()
                    ->route('admin.loan-applications.group-loans.show', $sourceApplication)
                    ->with('error', 'Only the assigned relationship manager (or an authorized assigner) can resubmit this application.');
            }

            $sourceMetadata = $this->applicationMetadata($sourceApplication);
            $sourceResolution = (string) data_get($sourceMetadata, 'rejection.resolution');
            if ($sourceApplication->status !== 'rejected' || $sourceResolution !== 'changes_requested') {
                return redirect()
                    ->route('admin.loan-applications.group-loans.show', $sourceApplication)
                    ->with('error', 'Only applications rejected for modifications can be edited and resubmitted.');
            }

            if ((int) $sourceApplication->loan_product_id !== (int) $loanProduct->id) {
                return redirect()
                    ->route('admin.loan-applications.group-loans.show', $sourceApplication)
                    ->with('error', 'The selected product does not match the application being revised.');
            }
        }

        $groupName = CustomerGroup::query()->find((int) $wizard['customer_group_id'])?->name ?? 'Unknown Group';
        $isResubmission = $sourceApplication instanceof GroupLoanApplication;
        DB::beginTransaction();

        try {
            $application = null;
            $resubmissionContext = [
                'revision_number' => null,
                'previous_member_count' => null,
                'new_member_count' => null,
                'previous_total_repayment_amount' => null,
                'new_total_repayment_amount' => null,
            ];

            if ($sourceApplication) {
                $applicationMetadata = $this->applicationMetadata($sourceApplication);
                $nextRevisionNumber = max(2, (int) data_get($applicationMetadata, 'revision_number', 1) + 1);
                $previousMemberCount = (int) $sourceApplication->members()->count();
                $previousTotalRepaymentAmount = (float) $sourceApplication->total_repayment_amount;

                data_set($applicationMetadata, 'installment_count', (int) ($wizard['installment_count'] ?? 0));
                data_set($applicationMetadata, 'duration_days', (int) ($wizard['duration_days'] ?? 0));
                data_set($applicationMetadata, 'relationship_manager_id', (int) $wizard['relationship_manager_id']);
                data_set($applicationMetadata, 'loan_term_value', isset($wizard['loan_term_value']) ? (int) $wizard['loan_term_value'] : null);
                data_set($applicationMetadata, 'loan_term_unit', $wizard['loan_term_unit'] ?? null);
                data_set($applicationMetadata, 'created_via', 'admin_group_loan_application');
                data_set($applicationMetadata, 'root_application_id', (int) data_get($applicationMetadata, 'root_application_id', $sourceApplication->id));
                data_set($applicationMetadata, 'revision_number', $nextRevisionNumber);
                data_set($applicationMetadata, 'rejection.status', 'resubmitted');
                data_set($applicationMetadata, 'rejection.resubmitted_at', now()->toIso8601String());
                data_set($applicationMetadata, 'rejection.resubmitted_by_admin_id', $admin->id);
                data_set($applicationMetadata, 'rejection.resubmitted_by_name', $admin->full_name);
                data_set($applicationMetadata, 'rejection.resolution', null);
                data_set($applicationMetadata, 'rejection.action_required', null);

                $sourceApplication->update([
                    'loan_product_id' => $loanProduct->id,
                    'customer_group_id' => (int) $wizard['customer_group_id'],
                    'relationship_manager_id' => (int) $wizard['relationship_manager_id'],
                    'group_name' => $groupName,
                    'loan_name' => $wizard['loan_name'],
                    'terms_and_conditions' => $wizard['terms_and_conditions'] ?? null,
                    'repayment_structure' => $wizard['repayment_structure'],
                    'start_date' => $wizard['start_date'],
                    'due_date' => $wizard['due_date'],
                    'processing_fee_percentage' => (float) $wizard['processing_fee_percentage'],
                    'monthly_interest_rate' => (float) $wizard['monthly_interest_rate'],
                    'arrears_rate' => (float) $wizard['arrears_rate'],
                    'total_principal_amount' => (float) data_get($wizard, 'totals.principal_amount', 0),
                    'total_processing_fee_amount' => (float) data_get($wizard, 'totals.processing_fee_amount', 0),
                    'total_interest_amount' => (float) data_get($wizard, 'totals.interest_amount', 0),
                    'total_repayment_amount' => (float) data_get($wizard, 'totals.repayment_amount', 0),
                    'total_disbursement_amount' => (float) data_get($wizard, 'totals.disbursement_amount', 0),
                    'status' => 'pending_approval',
                    'approved_by' => null,
                    'approved_at' => null,
                    'approval_notes' => null,
                    'submitted_at' => now(),
                    'metadata' => $applicationMetadata,
                ]);

                $sourceApplication->members()->delete();
                $sourceApplication->documents()->delete();
                $application = $sourceApplication->fresh();

                $resubmissionContext = [
                    'revision_number' => $nextRevisionNumber,
                    'previous_member_count' => $previousMemberCount,
                    'new_member_count' => count((array) $wizard['member_ids']),
                    'previous_total_repayment_amount' => $previousTotalRepaymentAmount,
                    'new_total_repayment_amount' => (float) data_get($wizard, 'totals.repayment_amount', 0),
                ];
            } else {
                $submittedTrailEntry = [
                    'action' => 'submitted',
                    'event_title' => 'Submitted for approval',
                    'notes' => 'Initial group loan application submission.',
                    'actor_admin_id' => $admin->id,
                    'actor_name' => $admin->full_name,
                    'at' => now()->toIso8601String(),
                    'status_after_event' => 'pending_approval',
                ];

                $application = GroupLoanApplication::create([
                    'loan_product_id' => $loanProduct->id,
                    'customer_group_id' => (int) $wizard['customer_group_id'],
                    'relationship_manager_id' => (int) $wizard['relationship_manager_id'],
                    'reference' => $this->generateReference(),
                    'group_name' => $groupName,
                    'loan_name' => $wizard['loan_name'],
                    'terms_and_conditions' => $wizard['terms_and_conditions'] ?? null,
                    'repayment_structure' => $wizard['repayment_structure'],
                    'start_date' => $wizard['start_date'],
                    'due_date' => $wizard['due_date'],
                    'processing_fee_percentage' => (float) $wizard['processing_fee_percentage'],
                    'monthly_interest_rate' => (float) $wizard['monthly_interest_rate'],
                    'arrears_rate' => (float) $wizard['arrears_rate'],
                    'total_principal_amount' => (float) data_get($wizard, 'totals.principal_amount', 0),
                    'total_processing_fee_amount' => (float) data_get($wizard, 'totals.processing_fee_amount', 0),
                    'total_interest_amount' => (float) data_get($wizard, 'totals.interest_amount', 0),
                    'total_repayment_amount' => (float) data_get($wizard, 'totals.repayment_amount', 0),
                    'total_disbursement_amount' => (float) data_get($wizard, 'totals.disbursement_amount', 0),
                    'status' => 'pending_approval',
                    'created_by' => auth('admin')->id(),
                    'submitted_at' => now(),
                    'metadata' => [
                        'installment_count' => (int) ($wizard['installment_count'] ?? 0),
                        'duration_days' => (int) ($wizard['duration_days'] ?? 0),
                        'relationship_manager_id' => (int) $wizard['relationship_manager_id'],
                        'loan_term_value' => isset($wizard['loan_term_value']) ? (int) $wizard['loan_term_value'] : null,
                        'loan_term_unit' => $wizard['loan_term_unit'] ?? null,
                        'created_via' => 'admin_group_loan_application',
                        'decision_trail' => [$submittedTrailEntry],
                        'revision_number' => 1,
                        'root_application_id' => 0,
                    ],
                ]);

                $metadata = $this->applicationMetadata($application);
                $metadata['root_application_id'] = $application->id;
                $application->update(['metadata' => $metadata]);
            }

            foreach ((array) $wizard['member_ids'] as $memberId) {
                $memberId = (int) $memberId;
                $calculation = data_get($wizard, "member_calculations.{$memberId}");
                if (! is_array($calculation)) {
                    throw ValidationException::withMessages([
                        'principals' => 'Calculated member amounts are missing for one or more members.',
                    ]);
                }

                $customer = Customer::query()->find($memberId);
                if (! $customer) {
                    throw ValidationException::withMessages([
                        'member_ids' => 'A selected customer could not be found during submission.',
                    ]);
                }

                GroupLoanApplicationMember::create([
                    'group_loan_application_id' => $application->id,
                    'customer_id' => $memberId,
                    'customer_group_id' => $customer->customer_group_id,
                    'group_member_title_id' => (int) data_get($wizard, "member_titles.{$memberId}"),
                    'principal_amount' => (float) $calculation['principal_amount'],
                    'calculated_processing_fee_amount' => (float) $calculation['processing_fee_amount'],
                    'calculated_interest_amount' => (float) $calculation['interest_amount'],
                    'calculated_arrears_basis_amount' => (float) $calculation['arrears_basis_amount'],
                    'calculated_total_repayment_amount' => (float) $calculation['total_repayment_amount'],
                    'disbursement_amount' => (float) $calculation['disbursement_amount'],
                    'disbursement_account_reference' => $customer->phone,
                    'disbursement_status' => 'pending',
                ]);
            }

            foreach ((array) ($wizard['documents'] ?? []) as $document) {
                GroupLoanApplicationDocument::create([
                    'group_loan_application_id' => $application->id,
                    'document_name' => (string) data_get($document, 'document_name'),
                    'file_path' => (string) data_get($document, 'file_path'),
                    'description' => data_get($document, 'description'),
                    'uploaded_by' => auth('admin')->id(),
                ]);
            }

            if ($isResubmission) {
                $this->appendDecisionTrail(
                    $application->fresh(),
                    'resubmitted',
                    'A revised application was submitted for approval.',
                    [
                        'event_title' => 'Revised application submitted',
                        'actor_admin_id' => $admin->id,
                        'actor_name' => $admin->full_name,
                        'status_after_event' => 'pending_approval',
                        'revision_number' => $resubmissionContext['revision_number'],
                        'previous_member_count' => $resubmissionContext['previous_member_count'],
                        'new_member_count' => $resubmissionContext['new_member_count'],
                        'previous_total_repayment_amount' => $resubmissionContext['previous_total_repayment_amount'],
                        'new_total_repayment_amount' => $resubmissionContext['new_total_repayment_amount'],
                    ]
                );
            }

            DB::commit();
            $this->clearWizard($request, $loanProduct);

            return redirect()
                ->route('admin.loan-applications.group-loans.show', $application)
                ->with('status', $isResubmission
                    ? 'Group loan application resubmitted successfully and is pending approval.'
                    : 'Group loan application submitted successfully and is pending approval.');
        } catch (\Throwable $e) {
            DB::rollBack();

            return redirect()
                ->route('admin.loan-applications.group-loans.review', $loanProduct)
                ->with('error', 'Failed to submit group loan application: '.$e->getMessage());
        }
    }

    public function show(GroupLoanApplication $groupLoanApplication): View
    {
        abort_unless(auth('admin')->user()?->can('loans.view'), 403);

        $groupLoanApplication->load([
            'loanProduct',
            'customerGroup',
            'relationshipManager',
            'creator',
            'approver',
            'documents.uploader',
            'members.customer',
            'members.groupMemberTitle',
            'members.loan',
        ]);

        $groupLoanApplication->syncDisbursementStatusFromMembers();

        return view('admin.loan-applications.group-loans.show', [
            'application' => $groupLoanApplication,
            'disbursementType' => config('app.disbursement_type', 'manual'),
        ]);
    }

    public function viewDocument(
        GroupLoanApplication $groupLoanApplication,
        GroupLoanApplicationDocument $groupLoanApplicationDocument
    ): BinaryFileResponse|RedirectResponse {
        abort_unless(auth('admin')->user()?->can('loans.view'), 403);
        $this->ensureDocumentBelongsToApplication($groupLoanApplication, $groupLoanApplicationDocument);

        $documentPath = (string) $groupLoanApplicationDocument->file_path;
        if ($documentPath === '' || ! Storage::disk('public')->exists($documentPath)) {
            return redirect()
                ->route('admin.loan-applications.group-loans.show', $groupLoanApplication)
                ->with('error', 'The requested document is missing from storage.');
        }

        return response()->file(
            Storage::disk('public')->path($documentPath),
            [
                'Content-Type' => Storage::disk('public')->mimeType($documentPath) ?: 'application/octet-stream',
                'Content-Disposition' => 'inline; filename="'.$this->buildDocumentDownloadName($groupLoanApplicationDocument).'"',
            ]
        );
    }

    public function downloadDocument(
        GroupLoanApplication $groupLoanApplication,
        GroupLoanApplicationDocument $groupLoanApplicationDocument
    ): BinaryFileResponse|RedirectResponse {
        abort_unless(auth('admin')->user()?->can('loans.view'), 403);
        $this->ensureDocumentBelongsToApplication($groupLoanApplication, $groupLoanApplicationDocument);

        $documentPath = (string) $groupLoanApplicationDocument->file_path;
        if ($documentPath === '' || ! Storage::disk('public')->exists($documentPath)) {
            return redirect()
                ->route('admin.loan-applications.group-loans.show', $groupLoanApplication)
                ->with('error', 'The requested document is missing from storage.');
        }

        return response()->download(
            Storage::disk('public')->path($documentPath),
            $this->buildDocumentDownloadName($groupLoanApplicationDocument)
        );
    }

    public function approve(Request $request, GroupLoanApplication $groupLoanApplication): RedirectResponse
    {
        abort_unless(
            auth('admin')->user()?->can('loans.approve') || auth('admin')->user()?->can('approvals.approve'),
            403
        );
        /** @var Admin $admin */
        $admin = auth('admin')->user();

        if ($groupLoanApplication->status !== 'pending_approval') {
            return redirect()
                ->route('admin.loan-applications.group-loans.show', $groupLoanApplication)
                ->with('error', 'Only pending group loan applications can be approved.');
        }

        DB::beginTransaction();

        try {
            $metadata = $this->applicationMetadata($groupLoanApplication);
            data_set($metadata, 'rejection.status', 'resolved');
            data_set($metadata, 'rejection.resolution', null);
            data_set($metadata, 'rejection.action_required', null);
            data_set($metadata, 'rejection.resolved_at', now()->toIso8601String());
            data_set($metadata, 'rejection.resolved_by_admin_id', $admin->id);
            data_set($metadata, 'rejection.resolved_by_name', $admin->full_name);

            $groupLoanApplication->update([
                'status' => 'awaiting_disbursement',
                'approved_by' => auth('admin')->id(),
                'approved_at' => now(),
                'approval_notes' => $request->input('notes'),
                'metadata' => $metadata,
            ]);

            $this->createMemberLoans($groupLoanApplication->fresh('members.customer'));
            $this->appendDecisionTrail(
                $groupLoanApplication->fresh(),
                'approved',
                $request->input('notes'),
                [
                    'event_title' => 'Application approved',
                    'actor_admin_id' => $admin->id,
                    'actor_name' => $admin->full_name,
                    'status_after_event' => 'awaiting_disbursement',
                ]
            );

            DB::commit();

            return redirect()
                ->route('admin.loan-applications.group-loans.show', $groupLoanApplication)
                ->with('status', 'Group loan application approved and moved to awaiting disbursement.');
        } catch (\Throwable $e) {
            DB::rollBack();

            return redirect()
                ->route('admin.loan-applications.group-loans.show', $groupLoanApplication)
                ->with('error', 'Failed to approve group loan application: '.$e->getMessage());
        }
    }

    public function reject(Request $request, GroupLoanApplication $groupLoanApplication): RedirectResponse
    {
        abort_unless(
            auth('admin')->user()?->can('loans.reject') || auth('admin')->user()?->can('approvals.reject'),
            403
        );
        /** @var Admin $admin */
        $admin = auth('admin')->user();

        if ($groupLoanApplication->status !== 'pending_approval') {
            return redirect()
                ->route('admin.loan-applications.group-loans.show', $groupLoanApplication)
                ->with('error', 'Only pending group loan applications can be rejected.');
        }

        $validated = $request->validate([
            'rejection_resolution' => ['required', 'in:changes_requested,rejected_permanent'],
            'notes' => ['required', 'string', 'max:5000'],
            'action_required' => ['nullable', 'string', 'max:5000', 'required_if:rejection_resolution,changes_requested'],
        ], [
            'action_required.required_if' => 'Please describe what the relationship manager must modify before resubmission.',
        ]);

        $resolution = (string) $validated['rejection_resolution'];
        $actionRequired = $resolution === 'changes_requested'
            ? trim((string) ($validated['action_required'] ?? ''))
            : null;

        $metadata = $this->applicationMetadata($groupLoanApplication);
        data_set($metadata, 'rejection', [
            'resolution' => $resolution,
            'action_required' => $actionRequired,
            'requested_at' => now()->toIso8601String(),
            'requested_by_admin_id' => $admin->id,
            'requested_by_name' => $admin->full_name,
            'status' => $resolution === 'changes_requested' ? 'open' : 'closed',
        ]);
        data_set($metadata, 'relationship_manager_follow_up', [
            'required' => $resolution === 'changes_requested',
            'relationship_manager_id' => (int) ($groupLoanApplication->relationship_manager_id ?? 0),
            'message' => $resolution === 'changes_requested'
                ? 'Reviewer requested modifications before approval. Please revise members/principal amounts and resubmit.'
                : null,
            'updated_at' => now()->toIso8601String(),
        ]);

        $groupLoanApplication->update([
            'status' => 'rejected',
            'approved_by' => auth('admin')->id(),
            'approved_at' => now(),
            'approval_notes' => $validated['notes'],
            'metadata' => $metadata,
        ]);

        $this->appendDecisionTrail(
            $groupLoanApplication->fresh(),
            $resolution,
            $validated['notes'],
            [
                'event_title' => $resolution === 'changes_requested'
                    ? 'Changes requested before approval'
                    : 'Application permanently rejected',
                'actor_admin_id' => $admin->id,
                'actor_name' => $admin->full_name,
                'action_required' => $actionRequired,
                'status_after_event' => 'rejected',
            ]
        );

        return redirect()
            ->route('admin.loan-applications.group-loans.show', $groupLoanApplication)
            ->with('status', $resolution === 'changes_requested'
                ? 'Application sent back for modifications. Relationship manager has clear action notes for resubmission.'
                : 'Group loan application permanently rejected.');
    }

    public function createRevisionDraft(Request $request, GroupLoanApplication $groupLoanApplication): RedirectResponse
    {
        abort_unless(auth('admin')->user()?->can('loans.create'), 403);
        /** @var Admin $admin */
        $admin = auth('admin')->user();

        $groupLoanApplication->loadMissing([
            'loanProduct',
            'members.groupMemberTitle',
            'documents',
        ]);

        if (! $this->canCreateRevisionDraft($admin, $groupLoanApplication)) {
            return redirect()
                ->route('admin.loan-applications.group-loans.show', $groupLoanApplication)
                ->with('error', 'Only the assigned relationship manager (or an authorized assigner) can modify this application and resubmit it.');
        }

        $metadata = $this->applicationMetadata($groupLoanApplication);
        $resolution = (string) data_get($metadata, 'rejection.resolution');

        if ($groupLoanApplication->status !== 'rejected' || $resolution !== 'changes_requested') {
            return redirect()
                ->route('admin.loan-applications.group-loans.show', $groupLoanApplication)
                ->with('error', 'Only applications rejected for modifications can be revised and resubmitted.');
        }

        $loanProduct = $groupLoanApplication->loanProduct;
        if (! $loanProduct || $loanProduct->category !== 'group_loans') {
            return redirect()
                ->route('admin.loan-applications.group-loans.show', $groupLoanApplication)
                ->with('error', 'The source application is not attached to a valid Group Loans product.');
        }

        $memberIds = $groupLoanApplication->members
            ->pluck('customer_id')
            ->map(fn ($id) => (int) $id)
            ->values();
        $memberTitles = $groupLoanApplication->members
            ->mapWithKeys(fn (GroupLoanApplicationMember $member) => [
                (int) $member->customer_id => (int) ($member->group_member_title_id ?? 0),
            ])
            ->all();
        $principals = $groupLoanApplication->members
            ->mapWithKeys(fn (GroupLoanApplicationMember $member) => [
                (int) $member->customer_id => (float) $member->principal_amount,
            ])
            ->all();

        if ($memberIds->count() < 3) {
            return redirect()
                ->route('admin.loan-applications.group-loans.show', $groupLoanApplication)
                ->with('error', 'Cannot create revision draft because at least 3 members are required.');
        }

        try {
            $calculation = $this->calculationService->calculate([
                'processing_fee_percentage' => (float) $groupLoanApplication->processing_fee_percentage,
                'monthly_interest_rate' => (float) $groupLoanApplication->monthly_interest_rate,
                'arrears_rate' => (float) $groupLoanApplication->arrears_rate,
                'repayment_structure' => (string) $groupLoanApplication->repayment_structure,
                'start_date' => optional($groupLoanApplication->start_date)->toDateString(),
                'due_date' => optional($groupLoanApplication->due_date)->toDateString(),
                'principals' => $principals,
            ]);
        } catch (\Throwable $e) {
            return redirect()
                ->route('admin.loan-applications.group-loans.show', $groupLoanApplication)
                ->with('error', 'Failed to prepare revision draft: '.$e->getMessage());
        }

        $documents = $groupLoanApplication->documents
            ->map(fn (GroupLoanApplicationDocument $document) => [
                'document_name' => (string) $document->document_name,
                'file_path' => (string) $document->file_path,
                'description' => $document->description,
            ])
            ->values()
            ->all();

        $nextRevision = max(2, (int) data_get($metadata, 'revision_number', 1) + 1);
        $rootApplicationId = (int) data_get($metadata, 'root_application_id', $groupLoanApplication->id);
        $storedLoanTermValue = data_get($metadata, 'loan_term_value');
        $storedLoanTermUnit = data_get($metadata, 'loan_term_unit');
        $loanTermValue = is_numeric($storedLoanTermValue) ? (int) $storedLoanTermValue : null;
        $loanTermUnit = in_array((string) $storedLoanTermUnit, ['weeks', 'months'], true)
            ? (string) $storedLoanTermUnit
            : null;

        if (! $loanTermValue || ! $loanTermUnit) {
            $inferredLoanTerm = $this->inferLoanTermFromDates(
                optional($groupLoanApplication->start_date)->toDateString(),
                optional($groupLoanApplication->due_date)->toDateString(),
            );
            $loanTermValue = $inferredLoanTerm['loan_term_value'];
            $loanTermUnit = $inferredLoanTerm['loan_term_unit'];
        }

        $wizard = [
            'customer_group_id' => (int) $groupLoanApplication->customer_group_id,
            'member_ids' => $memberIds->all(),
            'member_titles' => $memberTitles,
            'loan_name' => (string) $groupLoanApplication->loan_name,
            'terms_and_conditions' => $groupLoanApplication->terms_and_conditions,
            'start_date' => optional($groupLoanApplication->start_date)->toDateString(),
            'loan_term_value' => $loanTermValue,
            'loan_term_unit' => $loanTermUnit,
            'due_date' => optional($groupLoanApplication->due_date)->toDateString(),
            'repayment_structure' => (string) $groupLoanApplication->repayment_structure,
            'processing_fee_percentage' => (float) $groupLoanApplication->processing_fee_percentage,
            'monthly_interest_rate' => (float) $groupLoanApplication->monthly_interest_rate,
            'arrears_rate' => (float) $groupLoanApplication->arrears_rate,
            'relationship_manager_id' => (int) ($groupLoanApplication->relationship_manager_id ?? $admin->id),
            'principals' => $principals,
            'member_calculations' => collect($calculation['members'])
                ->mapWithKeys(fn ($member) => [(int) $member['customer_id'] => $member])
                ->all(),
            'totals' => $calculation['totals'],
            'installment_count' => $calculation['installment_count'],
            'duration_days' => $calculation['duration_days'],
            'documents' => $documents,
            'revision_source_application_id' => $groupLoanApplication->id,
            'root_application_id' => $rootApplicationId,
            'revision_number' => $nextRevision,
        ];

        $this->storeWizard($request, $loanProduct, $wizard);

        data_set($metadata, 'rejection.status', 'in_progress');
        data_set($metadata, 'rejection.revision_draft_started_at', now()->toIso8601String());
        data_set($metadata, 'rejection.revision_draft_started_by_admin_id', $admin->id);
        data_set($metadata, 'rejection.revision_draft_started_by_name', $admin->full_name);
        $groupLoanApplication->update(['metadata' => $metadata]);

        $this->appendDecisionTrail(
            $groupLoanApplication->fresh(),
            'revision_draft_created',
            'Modification workflow started from requested changes.',
            [
                'event_title' => 'Application modification started',
                'actor_admin_id' => $admin->id,
                'actor_name' => $admin->full_name,
                'target_revision_number' => $nextRevision,
                'status_after_event' => 'rejected',
            ]
        );

        return redirect()
            ->route('admin.loan-applications.group-loans.members', [
                'loanProduct' => $loanProduct,
                'customer_group_id' => $groupLoanApplication->customer_group_id,
            ])
            ->with('status', 'Modification mode is ready. Update members/amounts as instructed, then resubmit for approval.');
    }

    public function storeModificationNote(Request $request, GroupLoanApplication $groupLoanApplication): RedirectResponse
    {
        abort_unless(auth('admin')->user()?->can('loans.create'), 403);
        /** @var Admin $admin */
        $admin = auth('admin')->user();

        if (! $this->canAddModificationNote($admin, $groupLoanApplication)) {
            return $this->modificationNoteRedirect($request, $groupLoanApplication)
                ->with('error', 'Only the assigned relationship manager or original submitter can add modification notes.');
        }

        $metadata = $this->applicationMetadata($groupLoanApplication);
        $resolution = (string) data_get($metadata, 'rejection.resolution');
        if ($groupLoanApplication->status !== 'rejected' || $resolution !== 'changes_requested') {
            return $this->modificationNoteRedirect($request, $groupLoanApplication)
                ->with('error', 'Modification notes can only be added while the application is awaiting requested changes.');
        }

        $validated = $request->validate([
            'modification_note' => ['required', 'string', 'max:5000'],
        ]);

        $note = trim((string) $validated['modification_note']);
        data_set($metadata, 'relationship_manager_follow_up.last_note', $note);
        data_set($metadata, 'relationship_manager_follow_up.last_note_at', now()->toIso8601String());
        data_set($metadata, 'relationship_manager_follow_up.last_note_by_admin_id', $admin->id);
        data_set($metadata, 'relationship_manager_follow_up.last_note_by_name', $admin->full_name);
        data_set($metadata, 'relationship_manager_follow_up.updated_at', now()->toIso8601String());
        if ((string) data_get($metadata, 'rejection.status') === 'open') {
            data_set($metadata, 'rejection.status', 'in_progress');
        }
        $groupLoanApplication->update(['metadata' => $metadata]);

        $this->appendDecisionTrail(
            $groupLoanApplication->fresh(),
            'relationship_manager_note',
            $note,
            [
                'event_title' => 'Modification progress note',
                'actor_admin_id' => $admin->id,
                'actor_name' => $admin->full_name,
                'status_after_event' => 'rejected',
            ]
        );

        return $this->modificationNoteRedirect($request, $groupLoanApplication)
            ->with('status', 'Modification note added to the decision trail.');
    }

    public function disbursement(GroupLoanApplication $groupLoanApplication): View
    {
        abort_unless(auth('admin')->user()?->can('loans.disburse'), 403);

        $groupLoanApplication->load([
            'loanProduct',
            'customerGroup',
            'members.customer',
            'members.groupMemberTitle',
            'members.loan',
        ]);

        $groupLoanApplication->syncDisbursementStatusFromMembers();

        return view('admin.loan-applications.group-loans.disbursement', [
            'application' => $groupLoanApplication,
            'disbursementType' => config('app.disbursement_type', 'manual'),
        ]);
    }

    public function autoDisburse(GroupLoanApplication $groupLoanApplication): RedirectResponse
    {
        abort_unless(auth('admin')->user()?->can('loans.disburse'), 403);

        if (config('app.disbursement_type', 'manual') === 'manual') {
            return redirect()
                ->route('admin.loan-applications.group-loans.disbursement', $groupLoanApplication)
                ->with('error', 'Automated disbursement is disabled. Use manual disbursement per member loan.');
        }

        if (! in_array($groupLoanApplication->status, ['awaiting_disbursement', 'partially_disbursed'], true)) {
            return redirect()
                ->route('admin.loan-applications.group-loans.disbursement', $groupLoanApplication)
                ->with('error', 'This application is not ready for disbursement.');
        }

        $groupLoanApplication->load(['members.loan.customer', 'members.loan.loanProduct', 'members.loan.channel']);

        $processed = 0;

        DB::beginTransaction();

        try {
            foreach ($groupLoanApplication->members as $member) {
                $loan = $member->loan;
                if (! $loan || $loan->disbursement_status !== 'pending') {
                    continue;
                }

                $reference = 'AUTO-GL-'.$groupLoanApplication->id.'-'.$member->id.'-'.now()->format('YmdHis');

                $loan->disbursement_reference = $reference;
                $loan->disbursement_notes = 'Automated group loan disbursement.';
                $loan->applyDisbursementCompleted(now());
                $loan->save();

                $member->update([
                    'disbursement_status' => 'completed',
                    'disbursed_at' => $loan->disbursed_at,
                    'disbursement_reference' => $reference,
                    'disbursement_notes' => 'Automated group loan disbursement.',
                ]);

                $processed++;

                try {
                    $this->customerNotificationService->sendLoanDisbursed(
                        $loan->fresh(['customer', 'loanProduct', 'channel'])
                    );
                } catch (\Throwable) {
                    // Notification failures must not break disbursement state updates.
                }
            }

            DB::commit();

            $groupLoanApplication->refresh();
            $groupLoanApplication->syncDisbursementStatusFromMembers();

            return redirect()
                ->route('admin.loan-applications.group-loans.disbursement', $groupLoanApplication)
                ->with('status', "Automated disbursement completed for {$processed} member loan(s).");
        } catch (\Throwable $e) {
            DB::rollBack();

            return redirect()
                ->route('admin.loan-applications.group-loans.disbursement', $groupLoanApplication)
                ->with('error', 'Automated disbursement failed: '.$e->getMessage());
        }
    }

    private function ensureGroupLoanProduct(LoanProduct $loanProduct): void
    {
        if ($loanProduct->category !== 'group_loans') {
            abort(404, 'This product is not configured for group loan applications.');
        }
    }

    private function canCreateRevisionDraft(Admin $admin, GroupLoanApplication $groupLoanApplication): bool
    {
        if (! $admin->can('loans.create')) {
            return false;
        }

        if ($admin->can(self::ASSIGN_RELATIONSHIP_MANAGER_PERMISSION)) {
            return true;
        }

        return (int) $groupLoanApplication->relationship_manager_id === (int) $admin->id;
    }

    private function canAddModificationNote(Admin $admin, GroupLoanApplication $groupLoanApplication): bool
    {
        if (! $admin->can('loans.create')) {
            return false;
        }

        return (int) $groupLoanApplication->relationship_manager_id === (int) $admin->id
            || (int) $groupLoanApplication->created_by === (int) $admin->id;
    }

    private function modificationNoteRedirect(
        Request $request,
        GroupLoanApplication $groupLoanApplication
    ): RedirectResponse {
        $returnTo = (string) $request->input('return_to');
        $loanProductId = (int) $request->integer('loan_product_id');

        if (
            $returnTo === 'review'
            && $loanProductId > 0
            && $loanProductId === (int) $groupLoanApplication->loan_product_id
        ) {
            return redirect()->route('admin.loan-applications.group-loans.review', [
                'loanProduct' => $loanProductId,
            ]);
        }

        return redirect()->route('admin.loan-applications.group-loans.show', $groupLoanApplication);
    }

    /**
     * @return array<string, mixed>
     */
    private function applicationMetadata(GroupLoanApplication $groupLoanApplication): array
    {
        return is_array($groupLoanApplication->metadata) ? $groupLoanApplication->metadata : [];
    }

    /**
     * @param array<string, mixed> $extra
     */
    private function appendDecisionTrail(
        GroupLoanApplication $groupLoanApplication,
        string $action,
        ?string $notes = null,
        array $extra = []
    ): void {
        $metadata = $this->applicationMetadata($groupLoanApplication);
        $trail = collect(data_get($metadata, 'decision_trail', []))
            ->filter(fn ($entry) => is_array($entry))
            ->values()
            ->all();

        $admin = auth('admin')->user();
        $trail[] = array_merge([
            'action' => $action,
            'notes' => $notes,
            'at' => now()->toIso8601String(),
            'actor_admin_id' => $admin?->id,
            'actor_name' => $admin?->full_name,
        ], $extra);

        $metadata['decision_trail'] = $trail;
        $groupLoanApplication->update(['metadata' => $metadata]);
    }

    private function ensureDocumentBelongsToApplication(
        GroupLoanApplication $groupLoanApplication,
        GroupLoanApplicationDocument $groupLoanApplicationDocument
    ): void {
        if ((int) $groupLoanApplicationDocument->group_loan_application_id !== (int) $groupLoanApplication->id) {
            abort(404, 'The requested document does not belong to this group loan application.');
        }
    }

    private function buildDocumentDownloadName(GroupLoanApplicationDocument $groupLoanApplicationDocument): string
    {
        $extension = strtolower((string) pathinfo((string) $groupLoanApplicationDocument->file_path, PATHINFO_EXTENSION));
        $baseName = Str::slug(trim((string) $groupLoanApplicationDocument->document_name));

        if ($baseName === '') {
            $baseName = 'group-loan-supporting-document-'.$groupLoanApplicationDocument->id;
        }

        return $extension === '' ? $baseName : $baseName.'.'.$extension;
    }

    private function resolveRelationshipManagerId(Admin $admin, bool $canAssign, ?int $requestedRelationshipManagerId): int
    {
        if (! $canAssign) {
            if (! $admin->is_relationship_manager) {
                throw ValidationException::withMessages([
                    'relationship_manager_id' => 'You cannot proceed because your account is not marked as a relationship manager and you do not have permission to assign one.',
                ]);
            }

            return (int) $admin->id;
        }

        if ($requestedRelationshipManagerId) {
            $relationshipManager = Admin::query()
                ->where('id', $requestedRelationshipManagerId)
                ->where('is_relationship_manager', true)
                ->where('is_active', true)
                ->first();

            if (! $relationshipManager) {
                throw ValidationException::withMessages([
                    'relationship_manager_id' => 'The selected relationship manager is invalid or inactive.',
                ]);
            }

            return (int) $relationshipManager->id;
        }

        if ($admin->is_relationship_manager) {
            return (int) $admin->id;
        }

        throw ValidationException::withMessages([
            'relationship_manager_id' => 'Please select a relationship manager to continue.',
        ]);
    }

    /**
     * @return array{
     *     organization_name:string,
     *     tagline:string,
     *     address_lines:array<int,string>,
     *     contact_phone:string,
     *     contact_email:string,
     *     website_url:?string,
     *     display_website:?string,
     *     logo_data_uri:?string,
     *     logo_url:?string
     * }
     */
    private function buildPrintBranding(): array
    {
        $company = Company::query()
            ->where('is_primary', true)
            ->where('status', 'active')
            ->orderByDesc('id')
            ->first();

        $locationLine = collect([
            $company?->city ?: config('app.support_city'),
            $company?->state,
            $company?->postal_code,
        ])->filter()->implode(', ');

        $addressLines = collect([
            $company?->address_line1 ?: config('app.support_address_line1'),
            $company?->address_line2,
            $locationLine,
            $company?->country ?: config('app.support_country'),
        ])->filter(fn ($line) => trim((string) $line) !== '')->values()->all();

        $websiteUrl = config('app.website_url');
        $displayWebsite = $websiteUrl
            ? preg_replace('#^https?://#', '', rtrim((string) $websiteUrl, '/'))
            : null;

        return array_merge([
            'organization_name' => (string) ($company?->name ?: config('app.system_name', 'Loan Management System')),
            'tagline' => (string) config('app.system_tagline', 'Loan Management System'),
            'address_lines' => $addressLines,
            'contact_phone' => (string) ($company?->contact_phone ?: config('app.support_phone', 'N/A')),
            'contact_email' => (string) ($company?->contact_email ?: config('app.support_email', 'N/A')),
            'website_url' => $websiteUrl ? (string) $websiteUrl : null,
            'display_website' => $displayWebsite ? (string) $displayWebsite : null,
        ], $this->resolvePrintLogo());
    }

    /**
     * @return array{logo_data_uri:?string,logo_url:?string}
     */
    private function resolvePrintLogo(): array
    {
        $rawLogoPath = trim((string) config('app.system_logo_path', ''));
        $logoDataUri = null;
        $logoUrl = null;
        $logoCandidates = array_values(array_filter(array_unique([
            $rawLogoPath,
            'img/logo.png',
            'img/logo.png',
        ])));

        foreach ($logoCandidates as $logoCandidate) {
            if (Str::startsWith($logoCandidate, ['http://', 'https://', '//'])) {
                $logoUrl = $logoCandidate;
                break;
            }

            $logoPath = public_path(ltrim($logoCandidate, '/'));
            if (! is_file($logoPath)) {
                continue;
            }

            $extension = strtolower(pathinfo($logoPath, PATHINFO_EXTENSION));
            $mime = match ($extension) {
                'jpg', 'jpeg' => 'image/jpeg',
                'gif' => 'image/gif',
                'svg' => 'image/svg+xml',
                'webp' => 'image/webp',
                default => 'image/png',
            };

            $logoContent = file_get_contents($logoPath);
            if ($logoContent === false) {
                continue;
            }

            $logoDataUri = 'data:'.$mime.';base64,'.base64_encode($logoContent);
            break;
        }

        if ($logoDataUri === null && $logoUrl === null && $rawLogoPath !== '') {
            $logoUrl = asset(ltrim($rawLogoPath, '/'));
        }

        return [
            'logo_data_uri' => $logoDataUri,
            'logo_url' => $logoUrl,
        ];
    }

    /**
     * @return array<int|string, mixed>
     */
    private function wizard(Request $request, LoanProduct $loanProduct): array
    {
        return (array) $request->session()->get($this->wizardKey($loanProduct), []);
    }

    /**
     * @param array<int|string, mixed> $wizard
     */
    private function storeWizard(Request $request, LoanProduct $loanProduct, array $wizard): void
    {
        $request->session()->put($this->wizardKey($loanProduct), $wizard);
    }

    private function clearWizard(Request $request, LoanProduct $loanProduct): void
    {
        $request->session()->forget($this->wizardKey($loanProduct));
    }

    private function wizardKey(LoanProduct $loanProduct): string
    {
        return 'group_loan_wizard_'.$loanProduct->id.'_'.(string) auth('admin')->id();
    }

    private function generateReference(): string
    {
        do {
            $reference = 'GLA-'.now()->format('Ymd').'-'.Str::upper(Str::random(6));
        } while (GroupLoanApplication::query()->where('reference', $reference)->exists());

        return $reference;
    }

    private function computeDueDateFromLoanTerm(string $startDate, int $loanTermValue, string $loanTermUnit): string
    {
        $start = Carbon::parse($startDate)->startOfDay();

        return match ($loanTermUnit) {
            'weeks' => $start->copy()->addWeeks($loanTermValue)->toDateString(),
            'months' => $start->copy()->addMonths($loanTermValue)->toDateString(),
            default => throw ValidationException::withMessages([
                'loan_term_unit' => 'The selected loan term unit is invalid.',
            ]),
        };
    }

    /**
     * @return array{loan_term_value:int|null,loan_term_unit:string|null}
     */
    private function inferLoanTermFromDates(?string $startDate, ?string $dueDate): array
    {
        if (! $startDate || ! $dueDate) {
            return ['loan_term_value' => null, 'loan_term_unit' => null];
        }

        try {
            $start = Carbon::parse($startDate)->startOfDay();
            $due = Carbon::parse($dueDate)->startOfDay();
        } catch (\Throwable) {
            return ['loan_term_value' => null, 'loan_term_unit' => null];
        }

        if ($due->lessThanOrEqualTo($start)) {
            return ['loan_term_value' => null, 'loan_term_unit' => null];
        }

        $months = $start->diffInMonths($due);
        if (
            $months > 0
            && $start->copy()->addMonths($months)->toDateString() === $due->toDateString()
        ) {
            return ['loan_term_value' => $months, 'loan_term_unit' => 'months'];
        }

        $days = $start->diffInDays($due);
        if ($days > 0 && $days % 7 === 0) {
            return ['loan_term_value' => (int) ($days / 7), 'loan_term_unit' => 'weeks'];
        }

        return ['loan_term_value' => null, 'loan_term_unit' => null];
    }

    /**
     * @return array<int, array{period_number:int,due_date:Carbon,expected_amount:float}>
     */
    private function buildRepaymentSchedule(
        string $startDateValue,
        string $dueDateValue,
        string $repaymentStructure,
        float $totalRepaymentAmount,
        int $installmentCount
    ): array {
        if ($startDateValue === '' || $dueDateValue === '' || ! in_array($repaymentStructure, ['weekly', 'monthly'], true)) {
            return [];
        }

        $startDate = Carbon::parse($startDateValue)->startOfDay();
        $dueDate = Carbon::parse($dueDateValue)->startOfDay();
        if ($dueDate->lessThanOrEqualTo($startDate)) {
            return [];
        }

        if ($installmentCount <= 0) {
            $durationDays = max(1, $startDate->diffInDays($dueDate));
            $installmentCount = $repaymentStructure === 'weekly'
                ? (int) max(1, ceil($durationDays / 7))
                : (int) max(1, ceil($durationDays / 30));
        }

        $schedule = [];
        $remainingAmount = round(max(0, $totalRepaymentAmount), 2);
        $baseInstallmentAmount = $installmentCount > 0
            ? round($remainingAmount / $installmentCount, 2)
            : 0.0;
        $firstDueDate = $repaymentStructure === 'weekly'
            ? $startDate->copy()->addWeek()
            : $startDate->copy()->addMonth();

        for ($period = 1; $period <= $installmentCount; $period++) {
            $dueDateForPeriod = $repaymentStructure === 'weekly'
                ? $firstDueDate->copy()->addWeeks($period - 1)
                : $firstDueDate->copy()->addMonths($period - 1);

            if ($dueDateForPeriod->greaterThan($dueDate)) {
                $dueDateForPeriod = $dueDate->copy();
            }

            $expectedAmount = $period === $installmentCount
                ? $remainingAmount
                : min($remainingAmount, $baseInstallmentAmount);
            $expectedAmount = round($expectedAmount, 2);
            $remainingAmount = round(max(0, $remainingAmount - $expectedAmount), 2);

            $schedule[] = [
                'period_number' => $period,
                'due_date' => $dueDateForPeriod,
                'expected_amount' => $expectedAmount,
            ];
        }

        return $schedule;
    }

    /**
     * @param array<int|string, mixed> $memberCalculations
     * @return array<int, array<int, float>>
     */
    private function buildMemberInstallmentSchedules(array $memberCalculations, int $installmentCount): array
    {
        if ($installmentCount <= 0) {
            return [];
        }

        $schedules = [];

        foreach ($memberCalculations as $memberId => $calculation) {
            if (! is_array($calculation)) {
                continue;
            }

            $remainingAmount = round(max(0, (float) ($calculation['total_repayment_amount'] ?? 0)), 2);
            $baseInstallmentAmount = round($remainingAmount / $installmentCount, 2);
            $periods = [];

            for ($period = 1; $period <= $installmentCount; $period++) {
                $expectedAmount = $period === $installmentCount
                    ? $remainingAmount
                    : min($remainingAmount, $baseInstallmentAmount);
                $expectedAmount = round($expectedAmount, 2);
                $remainingAmount = round(max(0, $remainingAmount - $expectedAmount), 2);

                $periods[$period] = $expectedAmount;
            }

            $schedules[(int) $memberId] = $periods;
        }

        return $schedules;
    }

    /**
     * @param array<int, array{period_number:int,due_date:Carbon,expected_amount:float}> $repaymentSchedule
     * @param array<int, array<int, float>> $memberInstallmentSchedules
     * @return array<int, array{period_number:int,due_date:Carbon,expected_amount:float,member_breakdown:array<int,float>}>
     */
    private function appendMemberBreakdownToSchedule(array $repaymentSchedule, array $memberInstallmentSchedules): array
    {
        if ($repaymentSchedule === []) {
            return [];
        }

        $withBreakdown = [];

        foreach ($repaymentSchedule as $row) {
            $period = (int) ($row['period_number'] ?? 0);
            $memberBreakdown = [];
            $periodTotal = 0.0;

            foreach ($memberInstallmentSchedules as $memberId => $periodAmounts) {
                $memberAmount = round((float) ($periodAmounts[$period] ?? 0), 2);
                $memberBreakdown[(int) $memberId] = $memberAmount;
                $periodTotal += $memberAmount;
            }

            $row['member_breakdown'] = $memberBreakdown;
            if ($memberBreakdown !== []) {
                $row['expected_amount'] = round($periodTotal, 2);
            }

            $withBreakdown[] = $row;
        }

        return $withBreakdown;
    }

    private function createMemberLoans(GroupLoanApplication $application): void
    {
        $application->loadMissing('members.customer', 'loanProduct');

        $startDate = Carbon::parse($application->start_date);
        $dueDate = Carbon::parse($application->due_date);
        $durationDays = max(1, $startDate->diffInDays($dueDate));
        $installmentCount = $application->repayment_structure === 'weekly'
            ? (int) max(1, ceil($durationDays / 7))
            : (int) max(1, ceil($durationDays / 30));

        $firstPaymentDate = $application->repayment_structure === 'weekly'
            ? $startDate->copy()->addWeek()
            : $startDate->copy()->addMonth();

        // Group loan interest is captured upfront as a full-period amount.
        // Keep accrual rates at zero so no extra duration-based interest is accrued later.
        $dailyRate = 0.0;
        $weeklyRate = 0.0;

        foreach ($application->members as $member) {
            if ($member->loan_id) {
                continue;
            }

            $customer = $member->customer;
            if (! $customer) {
                continue;
            }

            $loan = Loan::create([
                'customer_id' => $customer->id,
                'loan_product_id' => $application->loan_product_id,
                'customer_group_id' => $member->customer_group_id ?? $application->customer_group_id,
                'loan_rate_id' => null,
                'channel_id' => null,
                'loan_number' => Loan::generateLoanNumber($application->loanProduct),
                'principal_amount' => $member->principal_amount,
                'processing_fee' => $member->calculated_processing_fee_amount,
                'processing_fee_percentage' => $application->processing_fee_percentage,
                'daily_rate' => $dailyRate,
                'weekly_rate' => $weeklyRate,
                'accrual_period' => $application->repayment_structure === 'weekly' ? 'weekly' : 'daily',
                'interest_accrued' => $member->calculated_interest_amount,
                'total_amount' => $member->calculated_total_repayment_amount,
                'amount_paid' => 0,
                'outstanding_balance' => $member->calculated_total_repayment_amount,
                'tenure_months' => $installmentCount,
                'loan_start_date' => $startDate,
                'loan_end_date' => $dueDate,
                'first_payment_date' => $firstPaymentDate,
                'last_payment_date' => $dueDate,
                'accrual_type' => 'daily',
                'last_accrual_date' => $startDate,
                'status' => 'approved',
                'disbursement_phone_number' => $customer->phone,
                'disbursement_status' => 'pending',
                'metadata' => [
                    'created_via' => 'group_loan_application',
                    'group_loan_application_id' => $application->id,
                    'group_loan_application_member_id' => $member->id,
                    'repayment_structure' => $application->repayment_structure,
                    'monthly_interest_rate' => (float) $application->monthly_interest_rate,
                    'arrears_rate' => (float) $application->arrears_rate,
                    'group_member_title_id' => $member->group_member_title_id,
                ],
            ]);

            $loan->createPaymentSchedule();

            $member->update([
                'loan_id' => $loan->id,
                'disbursement_status' => 'pending',
                'disbursement_amount' => $member->principal_amount,
            ]);
        }
    }
}
