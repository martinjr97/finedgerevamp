<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Migration\Dashboard\MigrationDashboardService;
use App\Migration\Dashboard\MigrationExceptionReportService;
use App\Migration\Dashboard\MigrationMappingReportService;
use App\Migration\Dashboard\MigrationReconciliationReportService;
use App\Migration\Dashboard\MigrationRunReportService;
use App\Migration\Phases\MigrationEntityMapRepository;
use App\Migration\RepaymentAttributionService;
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
            'customers' => $reports->paginateCustomers($request->only(['status', 'product', 'search'])),
            'filters' => $request->only(['status', 'product', 'search']),
        ]);
    }

    public function showCustomer(int $legacyUserId, MigrationReconciliationReportService $reports): View
    {
        $detail = $reports->customerDetail($legacyUserId);
        abort_if($detail === null, 404);

        return view('legacy.migration-dashboard.customers.show', [
            'legacyUserId' => $legacyUserId,
            'detail' => $detail,
        ]);
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

    public function identity(MigrationMappingReportService $mappings): View
    {
        return view('legacy.migration-dashboard.identity.index', [
            'resolutions' => $mappings->identityResolutions(),
        ]);
    }

    public function loans(Request $request, MigrationReconciliationReportService $reports): View
    {
        return view('legacy.migration-dashboard.loans.index', [
            'loans' => $reports->paginateLoans($request->only(['status', 'migration_status', 'search'])),
            'filters' => $request->only(['status', 'migration_status', 'search']),
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
            'repayments' => $reports->paginateRepayments($request->only(['classification', 'search'])),
            'filters' => $request->only(['classification', 'search']),
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
