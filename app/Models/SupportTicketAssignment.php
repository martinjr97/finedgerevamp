<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SupportTicketAssignment extends Model
{
    protected $fillable = [
        'support_ticket_id',
        'assigned_to_id',
        'assigned_by_id',
        'previous_assigned_to_id',
        'assigned_at',
        'note',
    ];

    protected $casts = [
        'assigned_at' => 'datetime',
    ];

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(SupportTicket::class, 'support_ticket_id');
    }

    public function assignedTo(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'assigned_to_id');
    }

    public function assignedBy(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'assigned_by_id');
    }

    public function previousAssignedTo(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'previous_assigned_to_id');
    }
}
