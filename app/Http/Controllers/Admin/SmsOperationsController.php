<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Sms\Services\SmsHealthService;
use Illuminate\View\View;

class SmsOperationsController extends Controller
{
    public function index(SmsHealthService $healthService): View
    {
        abort_unless(
            auth('admin')->user()?->can('sms-operations.view')
            || auth('admin')->user()?->can('sms-operations.manage'),
            403
        );

        $snapshot = $healthService->snapshot();
        $overall = $healthService->overallStatus($snapshot);

        $recentMessages = \App\Models\SmsMessage::query()
            ->latest()
            ->limit(25)
            ->get();

        return view('admin.sms-operations.index', [
            'snapshot' => $snapshot,
            'overall' => $overall,
            'recentMessages' => $recentMessages,
        ]);
    }
}
