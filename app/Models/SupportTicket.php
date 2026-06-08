<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class SupportTicket extends Model
{
    use HasFactory;

    public const STATUS_NEW = 'new';

    public const STATUS_IN_PROGRESS = 'in_progress';

    public const STATUS_RESOLVED = 'resolved';

    public const STATUS_CLOSED = 'closed';

    protected $fillable = [
        'customer_id',
        'name',
        'email',
        'phone',
        'subject',
        'message',
        'resolution_note',
        'status',
        'viewed_at',
        'resolved_at',
        'closed_at',
        'handled_by_admin_id',
        'assigned_to_id',
        'assigned_by_id',
        'assigned_at',
        'last_assigned_at',
    ];

    protected $casts = [
        'viewed_at' => 'datetime',
        'resolved_at' => 'datetime',
        'closed_at' => 'datetime',
        'assigned_at' => 'datetime',
        'last_assigned_at' => 'datetime',
    ];

    public static function statuses(): array
    {
        return [
            self::STATUS_NEW,
            self::STATUS_IN_PROGRESS,
            self::STATUS_RESOLVED,
            self::STATUS_CLOSED,
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function handler(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'handled_by_admin_id');
    }

    public function assignedTo(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'assigned_to_id');
    }

    public function assignedBy(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'assigned_by_id');
    }

    public function comments(): HasMany
    {
        return $this->hasMany(SupportTicketComment::class)->orderBy('created_at');
    }

    public function publicComments(): HasMany
    {
        return $this->comments()
            ->where(function ($query) {
                $query->where('is_visible_to_customer', true)
                    ->where('is_internal', false);
            });
    }

    public function internalComments(): HasMany
    {
        return $this->comments()->where('is_internal', true);
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(SupportTicketAssignment::class)->orderByDesc('assigned_at');
    }

    public function latestAssignment(): HasOne
    {
        return $this->hasOne(SupportTicketAssignment::class)->latestOfMany('assigned_at');
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(SupportTicketAttachment::class)->orderBy('created_at');
    }

    public function customerVisibleAttachments(): HasMany
    {
        return $this->attachments()->where('is_visible_to_customer', true);
    }

    public function isOpen(): bool
    {
        return in_array($this->status, [self::STATUS_NEW, self::STATUS_IN_PROGRESS], true);
    }

    public function isResolved(): bool
    {
        return $this->status === self::STATUS_RESOLVED;
    }

    public function isClosed(): bool
    {
        return $this->status === self::STATUS_CLOSED;
    }

    public function canCustomerComment(): bool
    {
        return ! $this->isResolved() && ! $this->isClosed();
    }

    public function ageForHumans(): string
    {
        return $this->created_at?->diffForHumans(['parts' => 2, 'short' => true]) ?? '—';
    }

    public function timeSinceLastAssignmentForHumans(): ?string
    {
        if (! $this->last_assigned_at) {
            return null;
        }

        return $this->last_assigned_at->diffForHumans(['parts' => 2, 'short' => true]);
    }

    public function assignedStaffName(): ?string
    {
        return $this->assignedTo?->full_name;
    }

    public function statusLabel(): string
    {
        return match ($this->status) {
            self::STATUS_NEW => 'New',
            self::STATUS_IN_PROGRESS => 'In Progress',
            self::STATUS_RESOLVED => 'Resolved',
            self::STATUS_CLOSED => 'Closed',
            default => ucfirst(str_replace('_', ' ', (string) $this->status)),
        };
    }

    public function statusColorClass(): string
    {
        return match ($this->status) {
            self::STATUS_NEW => 'bg-blue-500/20 text-blue-300 border-blue-400/60',
            self::STATUS_IN_PROGRESS => 'bg-amber-500/20 text-amber-300 border-amber-400/60',
            self::STATUS_RESOLVED => 'bg-emerald-500/20 text-emerald-300 border-emerald-400/60',
            self::STATUS_CLOSED => 'bg-slate-500/20 text-slate-300 border-slate-400/60',
            default => 'bg-slate-500/20 text-slate-300 border-slate-400/60',
        };
    }
}
