<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Communication extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'subject',
        'message',
        'type',
        'filters',
        'recipients_count',
        'sent_count',
        'failed_count',
        'status',
        'sent_at',
        'created_by',
        'error_message',
        'metadata',
        'is_sensitive',
    ];

    protected function casts(): array
    {
        return [
            'filters' => 'array',
            'metadata' => 'array',
            'sent_at' => 'datetime',
            'recipients_count' => 'integer',
            'sent_count' => 'integer',
            'failed_count' => 'integer',
            'is_sensitive' => 'boolean',
        ];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'created_by');
    }

    /**
     * Get the masked message for display (hides sensitive information like OTPs, PINs)
     */
    public function getMaskedMessageAttribute(): string
    {
        if (!$this->is_sensitive) {
            return $this->message;
        }

        $message = $this->message;

        // Mask 4-digit PINs with context (e.g., PIN: 1234, PIN is 1234, Your PIN: 1234)
        $message = preg_replace('/\bPIN[:\s]+(\d{4})\b/i', 'PIN: ****', $message);
        $message = preg_replace('/\bPIN\s+is\s+(\d{4})\b/i', 'PIN is ****', $message);
        $message = preg_replace('/\bYour\s+PIN[:\s]+(\d{4})\b/i', 'Your PIN: ****', $message);
        $message = preg_replace('/\bPIN[:\s]+(\d{4})\b/i', 'PIN: ****', $message);
        
        // Mask 6-digit OTPs with context (e.g., OTP: 123456, OTP is 123456, Your OTP: 123456)
        $message = preg_replace('/\bOTP[:\s]+(\d{6})\b/i', 'OTP: ******', $message);
        $message = preg_replace('/\bOTP\s+is\s+(\d{6})\b/i', 'OTP is ******', $message);
        $message = preg_replace('/\bYour\s+OTP[:\s]+(\d{6})\b/i', 'Your OTP: ******', $message);
        
        // Mask standalone 4-digit numbers that might be PINs (but be careful not to mask dates/times)
        // Only mask if it appears in a context suggesting it's a PIN
        $message = preg_replace('/\bPIN[:\s]+(\d{4})\b/i', 'PIN: ****', $message);
        
        // Mask standalone 6-digit numbers that might be OTPs (but be careful not to mask other numbers)
        // Only mask if it appears in a context suggesting it's an OTP
        $message = preg_replace('/\bOTP[:\s]+(\d{6})\b/i', 'OTP: ******', $message);
        
        // Mask password reset tokens/links (be more specific)
        $message = preg_replace('/reset[\/\?]token=([a-zA-Z0-9]{20,})/i', 'reset/token=****', $message);
        $message = preg_replace('/token=([a-zA-Z0-9]{20,})/i', 'token=****', $message);
        $message = preg_replace('/reset\/([a-zA-Z0-9]{20,})/i', 'reset/****', $message);
        
        // Mask long tokens (64+ characters) - password reset tokens
        $message = preg_replace('/\b([a-zA-Z0-9]{64,})\b/', '****', $message);
        
        // Mask URLs with tokens
        $message = preg_replace('/https?:\/\/[^\s]+\/([a-zA-Z0-9]{20,})/i', 'https://****/****', $message);
        
        // Mask passwords in plain text
        $message = preg_replace('/\bpassword[:\s]+([^\s]+)/i', 'password: ****', $message);
        $message = preg_replace('/\bnew\s+password[:\s]+([^\s]+)/i', 'new password: ****', $message);
        $message = preg_replace('/\btemporary\s+password[:\s]+([^\s]+)/i', 'temporary password: ****', $message);
        $message = preg_replace('/\bTemporary\s+Password[:\s]+([^\s]+)/i', 'Temporary Password: ****', $message);
        
        return $message;
    }

    /**
     * Get the masked subject for display
     */
    public function getMaskedSubjectAttribute(): string
    {
        if (!$this->is_sensitive) {
            return $this->subject ?? '';
        }

        $subject = $this->subject ?? '';
        
        // Mask OTPs and PINs in subject
        $subject = preg_replace('/\b(\d{4})\b/', '****', $subject);
        $subject = preg_replace('/\b(\d{6})\b/', '******', $subject);
        
        return $subject;
    }
}
