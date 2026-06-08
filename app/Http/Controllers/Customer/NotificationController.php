<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Models\Communication;
use App\Models\Customer;
use Illuminate\Contracts\View\View;

class NotificationController extends Controller
{
    public function index(): View
    {
        $customer = auth('customer')->user();

        $communications = Communication::query()
            ->where(function ($query) use ($customer) {
                $query->where(function ($recipientQuery) use ($customer) {
                    $recipientQuery
                        ->where('metadata->recipient->type', Customer::class)
                        ->where('metadata->recipient->id', $customer->id);
                })->orWhere('filters->customer_id', $customer->id);
            })
            ->orderByDesc('sent_at')
            ->orderByDesc('created_at')
            ->paginate(15);

        return view('customer.notifications.index', [
            'communications' => $communications,
        ]);
    }
}
