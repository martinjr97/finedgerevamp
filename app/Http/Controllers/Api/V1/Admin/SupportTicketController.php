<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\SupportTicket;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class SupportTicketController extends Controller
{
    /**
     * List support tickets
     */
    public function index(Request $request): JsonResponse
    {
        $admin = $request->user();
        
        // Note: There's no specific permission for support tickets in the permission matrix
        // Assuming admins can view support tickets. Adjust permission check as needed.
        
        $query = SupportTicket::with(['customer', 'handler'])->latest();

        if ($request->has('status') && $request->status) {
            $query->where('status', $request->status);
        }

        if ($request->has('customer_id') && $request->customer_id) {
            $query->where('customer_id', $request->customer_id);
        }

        if ($request->has('search') && $request->search) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('subject', 'like', "%{$search}%")
                    ->orWhere('message', 'like', "%{$search}%")
                    ->orWhere('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%");
            });
        }

        $perPage = min($request->get('per_page', 20), 100);
        $tickets = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => \App\Http\Resources\Api\V1\SupportTicketResource::collection($tickets),
            'meta' => [
                'current_page' => $tickets->currentPage(),
                'last_page' => $tickets->lastPage(),
                'per_page' => $tickets->perPage(),
                'total' => $tickets->total(),
            ],
        ]);
    }

    /**
     * Show a specific support ticket
     */
    public function show(SupportTicket $supportTicket): JsonResponse
    {
        $admin = request()->user();

        if (!$supportTicket->viewed_at) {
            $supportTicket->viewed_at = now();
            if ($supportTicket->status === SupportTicket::STATUS_NEW) {
                $supportTicket->status = SupportTicket::STATUS_IN_PROGRESS;
            }
            $supportTicket->handled_by_admin_id = $admin->id;
            $supportTicket->save();
        }

        $supportTicket->load(['customer', 'handler']);

        return response()->json([
            'success' => true,
            'data' => new \App\Http\Resources\Api\V1\SupportTicketResource($supportTicket),
        ]);
    }

    /**
     * Update support ticket status
     */
    public function update(Request $request, SupportTicket $supportTicket): JsonResponse
    {
        $admin = $request->user();

        if ($supportTicket->status === SupportTicket::STATUS_RESOLVED) {
            return response()->json([
                'success' => false,
                'message' => 'This ticket has already been resolved and can no longer be updated.',
            ], 422);
        }

        $validated = $request->validate([
            'status' => ['required', Rule::in(SupportTicket::statuses())],
            'resolution_note' => ['nullable', 'string', 'max:2000'],
        ]);

        if ($validated['status'] === SupportTicket::STATUS_RESOLVED && empty($validated['resolution_note'])) {
            return response()->json([
                'success' => false,
                'message' => 'Please provide resolution details before marking this ticket as resolved.',
                'errors' => [
                    'resolution_note' => ['Resolution note is required when marking as resolved.'],
                ],
            ], 422);
        }

        $supportTicket->status = $validated['status'];
        $supportTicket->handled_by_admin_id = $admin->id;

        if ($validated['status'] === SupportTicket::STATUS_RESOLVED) {
            if (!$supportTicket->resolved_at) {
                $supportTicket->resolved_at = now();
            }
            $supportTicket->resolution_note = $validated['resolution_note'];
        } else {
            $supportTicket->resolution_note = $validated['resolution_note'] ?? $supportTicket->resolution_note;
        }

        if (!$supportTicket->viewed_at) {
            $supportTicket->viewed_at = now();
        }

        $supportTicket->save();
        $supportTicket->load(['customer', 'handler']);

        return response()->json([
            'success' => true,
            'message' => 'Support ticket updated successfully.',
            'data' => new \App\Http\Resources\Api\V1\SupportTicketResource($supportTicket),
        ]);
    }
}

