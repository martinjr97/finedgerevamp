<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CompanyResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'code' => $this->code,
            'type' => $this->type,
            'registration_number' => $this->registration_number,
            'tpin' => $this->tpin,
            'status' => $this->status,
            'sector' => $this->whenLoaded('sector', function () {
                return [
                    'id' => $this->sector->id,
                    'name' => $this->sector->name,
                ];
            }),
            'relationship_manager' => $this->whenLoaded('relationshipManager', function () {
                return [
                    'id' => $this->relationshipManager->id,
                    'name' => $this->relationshipManager->full_name,
                    'email' => $this->relationshipManager->email,
                ];
            }),
            'contact_email' => $this->contact_email,
            'contact_phone' => $this->contact_phone,
            'address' => [
                'line1' => $this->address_line1,
                'line2' => $this->address_line2,
                'city' => $this->city,
                'state' => $this->state,
                'postal_code' => $this->postal_code,
                'country' => $this->country,
            ],
            'admins_count' => $this->when(isset($this->admins_count), $this->admins_count),
            'customers_count' => $this->when(isset($this->customers_count), $this->customers_count),
            'created_at' => $this->created_at->toIso8601String(),
            'updated_at' => $this->updated_at->toIso8601String(),
        ];
    }
}

