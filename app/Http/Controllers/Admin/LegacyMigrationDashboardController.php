<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Migration\Dashboard\MigrationCustomerMapService;
use App\Migration\Dashboard\MigrationIdentityResolutionService;
use App\Migration\Dashboard\MigrationCommandsGuide;
use App\Migration\Dashboard\MigrationDashboardService;
use App\Migration\Dashboard\MigrationExceptionReportService;
use App\Migration\Dashboard\MigrationMappingReportService;
use App\Migration\Dashboard\MigrationReconciliationReportService;
use App\Migration\Dashboard\MigrationRunReportService;
use App\Migration\Phases\MigrationEntityMapRepository;
use App\Migration\RepaymentAttributionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class LegacyMigrationDashboardController extends Controller
{
    public function index(MigrationDashboardService $dashboard): View
    {
        return view('legacy.migration-dashboard.index', [
            'summary' => $dashboard->homeSummary(),
        ]);
    }

    public function runs(MigrationRunReportService $runs): View
    {
        return view('legacy.migration-dashboard.runs.index', [
            'runs' => $runs->paginateRuns(),
        ]);
    }

    public function showRun(int $run, MigrationRunReportService $runs): View
    {
        $detail = $runs->findRun($run);
        abort_if($detail === null, 404);

        return view('legacy.migration-dashboard.runs.show', [
            'detail' => $detail,
        ]);
    }

    public function customers(Request $request, MigrationReconciliationReportService $reports): View
    {
        return view('legacy.migration-dashboard.customers.index', [
            'customers' => $reports->paginateCustomers($request->only(['status', 'product', 'search', 'run_id'])),
            'filters' => $request->only(['status', 'product', 'search', 'run_id']),
        ]);
    }

    public function showCustomer(int $legacyUserId, MigrationReconciliationReportService $reports): View
    {
        $detail = $reports->customerDetail($legacyUserId);
        abort_if($detail === null, 404);

        $backParams = array_filter([
            'run_id' => request()->integer('run_id') ?: null,
            'status' => request('status'),
        ]);

        return view('legacy.migration-dashboard.customers.show', [
            'legacyUserId' => $legacyUserId,
            'detail' => $detail,
            'backUrl' => route('legacy.migration-dashboard.customers.index', $backParams),
            'canManage' => auth('admin')->user()?->can('migration.manage') ?? false,
        ]);
    }

    public function mapCustomer(
        Request $request,
        int $legacyUserId,
        MigrationCustomerMapService $customerMaps,
    ): RedirectResponse {
        abort_unless(auth('admin')->user()?->can('migration.manage'), 403);

        $validated = $request->validate([
            'target_customer_id' => ['required', 'integer', 'exists:customers,id'],
            'reason' => ['nullable', 'string', 'max:2000'],
            'fields' => ['nullable', 'array'],
            'fields.*' => ['nullable', 'string', 'max:500'],
        ]);

        $result = $customerMaps->mapToExistingCustomer(
            $legacyUserId,
            (int) $validated['target_customer_id'],
            (int) auth('admin')->id(),
            $validated['reason'] ?? null,
            $validated['fields'] ?? [],
        );

        $backParams = array_filter([
            'run_id' => $request->integer('run_id') ?: null,
            'status' => $request->input('status'),
        ]);

        return redirect()
            ->route('legacy.migration-dashboard.customers.show', array_merge(['legacyUserId' => $legacyUserId], $backParams))
            ->with('status', $result['message']);
    }

    public function companies(Request $request, MigrationMappingReportService $mappings): View
    {
        return view('legacy.migration-dashboard.companies.index', [
            'companies' => $mappings->paginateCompanies($request->only(['classification', 'search', 'page'])),
            'filters' => $request->only(['classification', 'search']),
        ]);
    }

    public function marketeers(MigrationMappingReportService $mappings): View
    {
        return view('legacy.migration-dashboard.marketeers.index', [
            'data' => $mappings->marketeerSummary(),
        ]);
    }

    public function identity(MigrationIdentityResolutionService $identityResolutions): View
    {
        return view('legacy.migration-dashboard.identity.index', [
            'summary' => $identityResolutions->summary(),
            'pendingGroups' => $identityResolutions->pendingDuplicateGroups(),
            'resolutions' => $identityResolutions->approvedResolutions(),
            'canManage' => auth('admin')->user()?->can('migration.manage') ?? false,
        ]);
    }

    public function resolveIdentity(string $nrcKey, MigrationIdentityResolutionService $identityResolutions): View
    {
        $group = $identityResolutions->pendingGroupByNrcKey($nrcKey);
        abort_if($group === null, 404);

        return view('legacy.migration-dashboard.identity.resolve', [
            'group' => $group,
            'classifications' => [
                \App\Models\MigrationIdentityResolution::CLASS_SAME_PERSON_MAP_ONE => 'Merge — same person, map to one customer',
                \App\Models\MigrationIdentityResolution::CLASS_KEEP_SEPARATE => 'Keep separate — migrate as distinct customers (same NRC may need manual fix)',
                \App\Models\MigrationIdentityResolution::CLASS_EXCLUDE => 'Exclude — skip these legacy users during customer migration',
            ],
        ]);
    }

    public function storeIdentityResolution(Request $request, MigrationIdentityResolutionService $identityResolutions): RedirectResponse
    {
        abort_unless(auth('admin')->user()?->can('migration.manage'), 403);

        $validated = $request->validate([
            'nrc_key' => ['required', 'string'],
            'classification' => ['required', 'string'],
            'primary_legacy_user_id' => ['nullable', 'integer'],
            'target_customer_id' => ['nullable', 'integer'],
            'reason' => ['nullable', 'string', 'max:2000'],
        ]);

        $result = $identityResolutions->storeResolution($validated, (int) auth('admin')->id());

        $message = 'Identity resolution saved.';
        if ($result['duplicate_groups_resolved']) {
            $message .= ' All duplicate NRC groups are resolved — you may run migration:customers --promote.';
        } elseif ($result['pending_remaining'] > 0) {
            $message .= ' '.$result['pending_remaining'].' duplicate group(s) still need resolution.';
        }

        return redirect()
            ->route('legacy.migration-dashboard.identity.index')
            ->with('status', $message);
    }

    public function loans(Request $request, MigrationReconciliationReportService $reports): View
    {
        return view('legacy.migration-dashboard.loans.index', [
            'loans' => $reports->paginateLoans($request->only(['status', 'migration_status', 'search', 'run_id'])),
            'filters' => $request->only(['status', 'migration_status', 'search', 'run_id']),
        ]);
    }

    public function showLoan(int $legacyLoanId, MigrationReconciliationReportService $reports): View
    {
        $detail = $reports->loanDetail($legacyLoanId);
        abort_if($detail === null, 404);

        return view('legacy.migration-dashboard.loans.show', [
            'legacyLoanId' => $legacyLoanId,
            'detail' => $detail,
        ]);
    }

    public function repayments(Request $request, MigrationReconciliationReportService $reports, MigrationExceptionReportService $exceptions, MigrationDashboardService $dashboard): View
    {
        return view('legacy.migration-dashboard.repayments.index', [
            'repayments' => $reports->paginateRepayments($request->only(['classification', 'search', 'run_id'])),
            'filters' => $request->only(['classification', 'search', 'run_id']),
            'dManualBreakdown' => $exceptions->dManualBreakdown(),
            'classificationCounts' => $dashboard->repaymentClassificationSummary(),
            'classifications' => [
                RepaymentAttributionService::A_DIRECT,
                RepaymentAttributionService::B_RECONSTRUCTED,
                RepaymentAttributionService::C_AMBIGUOUS,
                RepaymentAttributionService::D_MANUAL,
            ],
        ]);
    }

    public function showRepayment(int $legacyRepaymentId, MigrationReconciliationReportService $reports): View
    {
        $detail = $reports->repaymentDetail($legacyRepaymentId);
        abort_if($detail === null || ! $detail['staging'], 404);

        return view('legacy.migration-dashboard.repayments.show', [
            'legacyRepaymentId' => $legacyRepaymentId,
            'detail' => $detail,
        ]);
    }

    public function exceptions(Request $request, MigrationExceptionReportService $exceptions): View
    {
        return view('legacy.migration-dashboard.exceptions.index', [
            'summary' => $exceptions->summary(),
            'exceptions' => $exceptions->paginateExceptions($request->only(['entity', 'run_id', 'search'])),
            'filters' => $request->only(['entity', 'run_id', 'search']),
        ]);
    }

    public function reconciliation(MigrationDashboardService $dashboard, MigrationReconciliationReportService $reports): View
    {
        return view('legacy.migration-dashboard.reconciliation.index', [
            'summary' => $reports->summary(),
            'home' => $dashboard->homeSummary(),
        ]);
    }

    public function commands(): View
    {
        return view('legacy.migration-dashboard.commands.index', [
            'phases' => MigrationCommandsGuide::phases(),
            'utilities' => MigrationCommandsGuide::utilities(),
            'executionOrder' => MigrationCommandsGuide::executionOrder(),
        ]);
    }

    public function mappings(Request $request, MigrationMappingReportService $mappings): View
    {
        $type = $request->string('type', MigrationEntityMapRepository::TYPE_PRODUCT)->toString();

        return view('legacy.migration-dashboard.mappings.index', [
            'type' => $type,
            'maps' => $mappings->paginateEntityMaps($type, $request->only(['search'])),
            'types' => [
                MigrationEntityMapRepository::TYPE_PRODUCT => 'Products',
                MigrationEntityMapRepository::TYPE_COMPANY => 'Companies',
                MigrationEntityMapRepository::TYPE_CUSTOMER_GROUP => 'Customer Groups',
                MigrationEntityMapRepository::TYPE_MARKET => 'Markets',
                MigrationEntityMapRepository::TYPE_FINANCIAL_INSTITUTION => 'Banks',
                MigrationEntityMapRepository::TYPE_WALLET_PROVIDER => 'Wallet Providers',
                MigrationEntityMapRepository::TYPE_BRANCH => 'Branches',
                MigrationEntityMapRepository::TYPE_RELATIONSHIP_MANAGER => 'Relationship Managers',
            ],
            'filters' => $request->only(['search']),
        ]);
    }
}
