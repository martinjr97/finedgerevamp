<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SupportTicketComment extends Model
{
    public const AUTHOR_CUSTOMER = 'customer';

    public const AUTHOR_ADMIN = 'admin';

    public const AUTHOR_STAFF = 'staff';

    public const AUTHOR_SYSTEM = 'system';

    protected $fillable = [
        'support_ticket_id',
        'author_type',
        'customer_id',
        'admin_id',
        'comment',
        'is_internal',
        'is_visible_to_customer',
        'metadata',
    ];

    protected $casts = [
        'is_internal' => 'boolean',
        'is_visible_to_customer' => 'boolean',
        'metadata' => 'array',
    ];

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(SupportTicket::class, 'support_ticket_id');
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function admin(): BelongsTo
    {
        return $this->belongsTo(Admin::class);
    }

    public function authorName(): string
    {
        return match ($this->author_type) {
            self::AUTHOR_CUSTOMER => $this->customer?->full_name
                ?? $this->ticket?->name
                ?? 'Customer',
            self::AUTHOR_STAFF => $this->admin?->full_name ?? 'Assigned Staff',
            self::AUTHOR_ADMIN => $this->admin?->full_name ?? 'Admin',
            self::AUTHOR_SYSTEM => 'System',
            default => 'Unknown',
        };
    }

    public function authorBadge(): string
    {
        return match ($this->author_type) {
            self::AUTHOR_CUSTOMER => 'Customer',
            self::AUTHOR_STAFF => 'Assigned Staff',
            self::AUTHOR_ADMIN => 'Admin',
            self::AUTHOR_SYSTEM => 'System',
            default => ucfirst($this->author_type),
        };
    }

    public function visibleToCustomer(): bool
    {
        if ($this->author_type === self::AUTHOR_SYSTEM) {
            return (bool) ($this->metadata['customer_visible'] ?? $this->is_visible_to_customer);
        }

        return $this->is_visible_to_customer && ! $this->is_internal;
    }

    public function scopeCustomerVisible($query)
    {
        return $query->where(function ($q) {
            $q->where(function ($inner) {
                $inner->where('is_visible_to_customer', true)
                    ->where('is_internal', false);
            })->orWhere(function ($inner) {
                $inner->where('author_type', self::AUTHOR_SYSTEM)
                    ->where('is_visible_to_customer', true);
            });
        });
    }
}
