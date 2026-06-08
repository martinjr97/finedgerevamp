<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Services\CustomerLifetimeStatementService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CustomerStatementController extends Controller
{
    public function __construct(
        private readonly CustomerLifetimeStatementService $statementService
    ) {}

    public function show(Request $request, Customer $customer): View
    {
        abort_unless(auth('admin')->user()?->can('customers.view'), 403);

        $fromDate = $request->filled('from_date') ? Carbon::parse($request->input('from_date'))->startOfDay() : null;
        $toDate = $request->filled('to_date') ? Carbon::parse($request->input('to_date'))->endOfDay() : null;
        $loanId = $request->filled('loan_id') ? (int) $request->input('loan_id') : null;

        $statement = $this->statementService->build($customer, $fromDate, $toDate, $loanId);
        $isPrint = $request->boolean('print');

        return view($isPrint ? 'admin.customers.statement-print' : 'admin.customers.statement', [
            'customer' => $customer,
            'statement' => $statement,
            'isPrint' => $isPrint,
        ]);
    }
}
