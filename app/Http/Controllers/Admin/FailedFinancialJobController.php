<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\PaymentPlatform\Services\FailedFinancialJobService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class FailedFinancialJobController extends Controller
{
    public function index(FailedFinancialJobService $service): View
    {
        abort_unless(
            auth('admin')->user()?->can('payment-gateways.view')
            || auth('admin')->user()?->can('payment-gateways.manage'),
            403
        );

        return view('admin.payment-operations.failed-jobs', [
            'failedJobs' => $service->list(100),
        ]);
    }

    public function show(string $uuid, FailedFinancialJobService $service): View
    {
        abort_unless(
            auth('admin')->user()?->can('payment-gateways.view')
            || auth('admin')->user()?->can('payment-gateways.manage'),
            403
        );

        $job = $service->find($uuid);
        abort_if($job === null, 404);

        return view('admin.payment-operations.failed-job-show', [
            'job' => $job,
        ]);
    }

    public function retry(string $uuid, FailedFinancialJobService $service): RedirectResponse
    {
        abort_unless(auth('admin')->user()?->can('payment-gateways.manage'), 403);

        abort_unless($service->retry($uuid), 404);

        return back()->with('status', 'Failed job queued for retry.');
    }

    public function discard(Request $request, string $uuid, FailedFinancialJobService $service): RedirectResponse
    {
        abort_unless(auth('admin')->user()?->can('payment-gateways.manage'), 403);

        $request->validate([
            'confirm' => ['accepted'],
        ]);

        abort_unless($service->discard($uuid), 404);

        return redirect()
            ->route('admin.payment-operations.failed-jobs.index')
            ->with('status', 'Failed job discarded.');
    }
}
