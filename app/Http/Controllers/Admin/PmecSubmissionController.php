<?php

namespace App\Http\Controllers\Admin;

use App\Exports\PmecSubmissionExport;
use App\Http\Controllers\Controller;
use App\Models\CustomerGroup;
use App\Models\Loan;
use App\Models\LoanProduct;
use App\Models\PmecSubmission;
use App\Models\PmecSubmissionItem;
use App\Services\PmecSubmissionService;
use App\Support\PmecSubmissionDefaults;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PmecSubmissionController extends Controller
{
    public function __construct(
        private readonly PmecSubmissionService $pmecService,
    ) {}

    public function index(): View
    {
        abort_unless(auth('admin')->user()?->can('pmec_submissions.view'), 403);

        $submissions = PmecSubmission::query()
            ->with(['loanProduct', 'customerGroup', 'generatedBy'])
            ->withCount('items')
            ->latest()
            ->paginate(20);

        return view('admin.pmec-submissions.index', [
            'submissions' => $submissions,
        ]);
    }

    public function create(Request $request): View
    {
        abort_unless(auth('admin')->user()?->can('pmec_submissions.create'), 403);

        $products = LoanProduct::query()
            ->where('category', 'government')
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        $selectedProductId = (int) $request->query('loan_product_id', old('loan_product_id', 0));
        $groups = $selectedProductId > 0
            ? CustomerGroup::query()
                ->where('loan_product_id', $selectedProductId)
                ->where('is_active', true)
                ->orderBy('name')
                ->get()
            : collect();

        return view('admin.pmec-submissions.create', [
            'products' => $products,
            'groups' => $groups,
            'modes' => PmecSubmissionDefaults::modes(),
            'selectedProductId' => $selectedProductId,
        ]);
    }

    public function preview(Request $request): View|RedirectResponse
    {
        abort_unless(auth('admin')->user()?->can('pmec_submissions.create'), 403);

        $validated = $this->validateSubmissionForm($request);
        $product = LoanProduct::query()->findOrFail($validated['loan_product_id']);

        if ($product->category !== 'government') {
            return back()->withErrors(['loan_product_id' => 'Only government loan products can be used for PMEC submissions.'])->withInput();
        }

        $rows = $this->pmecService->buildPreviewRows(
            $product,
            $validated['submission_month'],
            $validated['mode'],
            $validated['customer_group_ids'] ?? null,
            $validated['loan_ids'] ?? null,
        );

        if ($rows->isEmpty()) {
            return back()->with('warning', 'No eligible loans match the selected criteria.')->withInput();
        }

        return view('admin.pmec-submissions.preview', [
            'product' => $product,
            'rows' => $rows,
            'form' => $validated,
            'modes' => PmecSubmissionDefaults::modes(),
        ]);
    }

    public function generate(Request $request): RedirectResponse
    {
        abort_unless(auth('admin')->user()?->can('pmec_submissions.export'), 403);

        $validated = $this->validateSubmissionForm($request);
        $validated['exclude_invalid'] = $request->boolean('exclude_invalid');
        $selectedLoanIds = array_map('intval', $request->input('loan_ids', []));

        if ($selectedLoanIds === []) {
            return back()->withErrors(['loan_ids' => 'Select at least one loan to include in the export.'])->withInput();
        }

        $product = LoanProduct::query()->findOrFail($validated['loan_product_id']);

        $rows = $this->pmecService->buildPreviewRows(
            $product,
            $validated['submission_month'],
            $validated['mode'],
            $validated['customer_group_ids'] ?? null,
            $validated['loan_ids'] ?? null,
        )->filter(fn (array $row) => in_array($row['loan_id'], $selectedLoanIds, true));

        if (! $validated['exclude_invalid']) {
            try {
                $this->pmecService->assertNoInvalidRows($rows);
            } catch (\InvalidArgumentException $e) {
                return back()->withErrors(['export' => $e->getMessage()])->withInput();
            }
        } else {
            $rows = $rows->filter(fn (array $row) => $row['is_valid'])->values();
        }

        if ($rows->isEmpty()) {
            return back()->withErrors(['export' => 'No valid loans selected for export.'])->withInput();
        }

        $submission = DB::transaction(function () use ($validated, $product, $rows) {
            $submission = PmecSubmission::query()->create([
                'batch_number' => $this->pmecService->generateBatchNumber($validated['submission_month']),
                'loan_product_id' => $product->id,
                'customer_group_id' => $this->pmecService->resolveStoredGroupId($validated['customer_group_ids'] ?? null),
                'submission_month' => $validated['submission_month'],
                'mode' => $validated['mode'],
                'status' => PmecSubmissionDefaults::SUBMISSION_STATUS_GENERATED,
                'generated_by' => auth('admin')->id(),
                'generated_at' => now(),
                'notes' => $validated['notes'] ?? null,
            ]);

            foreach ($rows as $row) {
                $payload = $row['payload'];
                $previousItemId = $this->pmecService->latestFailedItemId($row['loan_id']);
                $itemStatus = $previousItemId
                    ? PmecSubmissionDefaults::ITEM_STATUS_RESUBMITTED
                    : PmecSubmissionDefaults::ITEM_STATUS_GENERATED;

                PmecSubmissionItem::query()->create([
                    'pmec_submission_id' => $submission->id,
                    'loan_id' => $row['loan_id'],
                    'customer_id' => $row['customer_id'],
                    'pernr' => $payload['pernr'],
                    'nrc' => $payload['nrc'],
                    'first_name' => $payload['first_name'],
                    'surname' => $payload['surname'],
                    'begda' => $payload['begda'],
                    'endda' => $payload['endda'],
                    'betrg' => $payload['betrg'],
                    'lgart' => $payload['lgart'],
                    'emfsl' => $payload['emfsl'],
                    'zlsch' => $payload['zlsch'],
                    'status' => $itemStatus,
                    'previous_submission_item_id' => $previousItemId,
                ]);
            }

            $submission->load('items');

            $filename = $this->pmecService->exportFilename($validated['submission_month']);
            $excelRows = $this->pmecService->excelRows($submission->items);
            $total = (float) $submission->items->sum('betrg');

            $relativePath = 'pmec-submissions/'.$filename;
            Excel::store(
                new PmecSubmissionExport($excelRows, $total),
                $relativePath,
                'local',
            );

            $submission->update(['file_path' => $relativePath]);

            return $submission;
        });

        return redirect()
            ->route('admin.pmec-submissions.show', $submission)
            ->with('success', 'PMEC submission file generated successfully.');
    }

    public function show(PmecSubmission $pmecSubmission): View
    {
        abort_unless(auth('admin')->user()?->can('pmec_submissions.view'), 403);

        $pmecSubmission->load([
            'loanProduct',
            'customerGroup',
            'generatedBy',
            'items.loan',
            'items.customer',
        ]);

        return view('admin.pmec-submissions.show', [
            'submission' => $pmecSubmission,
            'modes' => PmecSubmissionDefaults::modes(),
            'submissionStatuses' => PmecSubmissionDefaults::submissionStatuses(),
            'itemStatuses' => PmecSubmissionDefaults::itemStatuses(),
        ]);
    }

    public function download(PmecSubmission $pmecSubmission): BinaryFileResponse|StreamedResponse
    {
        abort_unless(auth('admin')->user()?->can('pmec_submissions.export'), 403);

        abort_unless(
            $pmecSubmission->file_path && Storage::disk('local')->exists($pmecSubmission->file_path),
            404,
            'PMEC submission file not found.',
        );

        return response()->download(
            Storage::disk('local')->path($pmecSubmission->file_path),
            basename($pmecSubmission->file_path),
        );
    }

    public function markItemFailed(Request $request, PmecSubmissionItem $item): RedirectResponse
    {
        abort_unless(auth('admin')->user()?->can('pmec_submissions.mark_failed'), 403);

        $validated = $request->validate([
            'failure_reason' => ['nullable', 'string', 'max:1000'],
        ]);

        $item->update([
            'status' => PmecSubmissionDefaults::ITEM_STATUS_FAILED,
            'failure_reason' => $validated['failure_reason'] ?? null,
        ]);

        return back()->with('success', 'Submission item marked as failed. It can be included in a future resubmission batch.');
    }

    public function markItemSubmitted(PmecSubmissionItem $item): RedirectResponse
    {
        abort_unless(auth('admin')->user()?->can('pmec_submissions.export'), 403);

        $item->update([
            'status' => PmecSubmissionDefaults::ITEM_STATUS_SUBMITTED,
        ]);

        return back()->with('success', 'Submission item marked as successfully submitted to PMEC.');
    }

    /**
     * @return array<string, mixed>
     */
    private function validateSubmissionForm(Request $request): array
    {
        $validated = $request->validate([
            'loan_product_id' => ['required', 'exists:loan_products,id'],
            'customer_group_ids' => ['nullable', 'array'],
            'customer_group_ids.*' => ['integer', 'exists:customer_groups,id'],
            'submission_month' => ['required', 'date_format:Y-m'],
            'mode' => ['required', Rule::in(PmecSubmissionDefaults::modeValues())],
            'loan_ids' => ['nullable', 'array'],
            'loan_ids.*' => ['integer', 'exists:loans,id'],
            'manual_loan_numbers' => ['nullable', 'string'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $validated['loan_ids'] = $this->resolveManualLoanIds($request);

        return $validated;
    }

    /**
     * @return list<int>|null
     */
    private function resolveManualLoanIds(Request $request): ?array
    {
        if ($request->input('mode') !== PmecSubmissionDefaults::MODE_MANUAL) {
            $ids = $request->input('loan_ids');

            return is_array($ids) && $ids !== [] ? array_map('intval', $ids) : null;
        }

        $loanIds = array_map('intval', (array) $request->input('loan_ids', []));
        $numbers = collect(preg_split('/[\s,]+/', (string) $request->input('manual_loan_numbers', ''), -1, PREG_SPLIT_NO_EMPTY))
            ->map(fn (string $n) => trim($n))
            ->filter()
            ->all();

        if ($numbers !== []) {
            $fromNumbers = Loan::query()
                ->whereIn('loan_number', $numbers)
                ->pluck('id')
                ->all();
            $loanIds = array_values(array_unique(array_merge($loanIds, $fromNumbers)));
        }

        return $loanIds !== [] ? $loanIds : null;
    }
}
