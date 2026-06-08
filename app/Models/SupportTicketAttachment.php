<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class SupportTicketAttachment extends Model
{
    public const UPLOADER_ADMIN = 'admin';

    public const UPLOADER_CUSTOMER = 'customer';

    public const UPLOADER_GUEST = 'guest';

    protected $fillable = [
        'support_ticket_id',
        'uploader_type',
        'admin_id',
        'customer_id',
        'original_name',
        'path',
        'mime_type',
        'size_bytes',
        'is_visible_to_customer',
    ];

    protected $casts = [
        'is_visible_to_customer' => 'boolean',
        'size_bytes' => 'integer',
    ];

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(SupportTicket::class, 'support_ticket_id');
    }

    public function admin(): BelongsTo
    {
        return $this->belongsTo(Admin::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function isImage(): bool
    {
        return str_starts_with((string) $this->mime_type, 'image/');
    }

    public function isPdf(): bool
    {
        return $this->mime_type === 'application/pdf';
    }

    public function formattedSize(): string
    {
        $bytes = $this->size_bytes;

        if ($bytes < 1024) {
            return $bytes.' B';
        }

        if ($bytes < 1024 * 1024) {
            return number_format($bytes / 1024, 1).' KB';
        }

        return number_format($bytes / (1024 * 1024), 1).' MB';
    }

    public function downloadUrl(): ?string
    {
        if (! Storage::disk('public')->exists($this->path)) {
            return null;
        }

        return Storage::disk('public')->url($this->path);
    }

    public function uploaderLabel(): string
    {
        return match ($this->uploader_type) {
            self::UPLOADER_ADMIN => $this->admin?->full_name ?? 'Admin',
            self::UPLOADER_CUSTOMER => $this->customer?->full_name ?? 'Customer',
            self::UPLOADER_GUEST => 'Guest',
            default => ucfirst($this->uploader_type),
        };
    }
}
