<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SupportTicketResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone,
            'subject' => $this->subject,
            'message' => $this->message,
            'status' => $this->status,
            'resolution_note' => $this->resolution_note,
            'customer' => $this->whenLoaded('customer', function () {
                return [
                    'id' => $this->customer->id,
                    'name' => $this->customer->full_name,
                    'email' => $this->customer->email,
                ];
            }),
            'handler' => $this->whenLoaded('handler', function () {
                return [
                    'id' => $this->handler->id,
                    'name' => $this->handler->full_name,
                    'email' => $this->handler->email,
                ];
            }),
            'assigned_to' => $this->whenLoaded('assignedTo', function () {
                return [
                    'id' => $this->assignedTo->id,
                    'name' => $this->assignedTo->full_name,
                    'email' => $this->assignedTo->email,
                ];
            }),
            'assigned_at' => $this->assigned_at?->toIso8601String(),
            'last_assigned_at' => $this->last_assigned_at?->toIso8601String(),
            'closed_at' => $this->closed_at?->toIso8601String(),
            'viewed_at' => $this->viewed_at?->toIso8601String(),
            'resolved_at' => $this->resolved_at?->toIso8601String(),
            'created_at' => $this->created_at->toIso8601String(),
            'updated_at' => $this->updated_at->toIso8601String(),
        ];
    }
}

