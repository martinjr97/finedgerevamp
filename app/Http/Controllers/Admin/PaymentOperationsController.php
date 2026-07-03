<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\PaymentPlatform\Services\FailedFinancialJobService;
use App\PaymentPlatform\Services\GatewayOperationsMetricsService;
use Illuminate\View\View;

class PaymentOperationsController extends Controller
{
    public function index(
        GatewayOperationsMetricsService $metricsService,
        FailedFinancialJobService $failedFinancialJobService,
    ): View {
        abort_unless(
            auth('admin')->user()?->can('payment-gateways.view')
            || auth('admin')->user()?->can('payment-gateways.manage'),
            403
        );

        $metrics = $metricsService->operationalSnapshot();
        $recentFailedJobs = $failedFinancialJobService->list(10);

        return view('admin.payment-operations.index', [
            'metrics' => $metrics,
            'recentFailedJobs' => $recentFailedJobs,
        ]);
    }
}
