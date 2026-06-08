<?php

namespace App\Support;

use App\Models\Admin;
use App\Models\Communication;
use App\Models\Customer;
use Illuminate\Support\Facades\Auth;

class CommunicationLogger
{
    /**
     * Log a system-generated communication (password reset, OTP, etc.)
     * 
     * @param string $subject Email subject
     * @param string $message Message content
     * @param string $type 'email', 'sms', or 'both'
     * @param bool $isSensitive Whether the message contains sensitive information
     * @param Admin|Customer|null $recipient The recipient (Admin or Customer)
     * @param Admin|null $createdBy The admin who initiated this (null for system)
     * @param array $metadata Additional metadata
     * @return Communication
     */
    public static function log(
        string $subject,
        string $message,
        string $type = 'email',
        bool $isSensitive = false,
        Admin|Customer|null $recipient = null,
        ?Admin $createdBy = null,
        array $metadata = []
    ): Communication {
        // Store original message - the Communication model's accessor will mask it for display
        // We store the original so it can be masked dynamically when viewed
        $storedMessage = $message;
        $storedSubject = $subject;

        // Determine recipient info
        $recipientEmail = null;
        $recipientPhone = null;
        $recipientName = null;
        
        if ($recipient instanceof Admin) {
            $recipientEmail = $recipient->email;
            $recipientPhone = $recipient->phone;
            $recipientName = $recipient->full_name;
        } elseif ($recipient instanceof Customer) {
            $recipientEmail = $recipient->email;
            $recipientPhone = $recipient->phone;
            $recipientName = $recipient->full_name;
        }

        // Get creator (default to current admin, or system if none)
        $creatorId = $createdBy?->id ?? Auth::guard('admin')->id();

        // Add recipient info to metadata
        $metadata['recipient'] = [
            'type' => $recipient ? get_class($recipient) : null,
            'id' => $recipient?->id,
            'email' => $recipientEmail,
            'phone' => $recipientPhone ? self::maskPhone($recipientPhone) : null,
            'name' => $recipientName,
        ];
        $metadata['original_message'] = $isSensitive ? $message : null; // Store original only if sensitive
        $metadata['is_system_generated'] = true;

        return Communication::create([
            'subject' => $storedSubject,
            'message' => $storedMessage, // Store original, masking happens via accessor
            'type' => $type,
            'recipients_count' => 1,
            'sent_count' => 1, // Assume sent successfully
            'failed_count' => 0,
            'status' => 'completed',
            'sent_at' => now(),
            'created_by' => $creatorId,
            'is_sensitive' => $isSensitive,
            'metadata' => $metadata,
        ]);
    }

    /**
     * Mask sensitive data in message content
     */
    private static function maskSensitiveData(string $content): string
    {
        // Mask 4-digit PINs
        $content = preg_replace('/\bPIN[:\s]+(\d{4})\b/i', 'PIN: ****', $content);
        $content = preg_replace('/\bPIN\s+is\s+(\d{4})\b/i', 'PIN is ****', $content);
        $content = preg_replace('/\bYour\s+PIN[:\s]+(\d{4})\b/i', 'Your PIN: ****', $content);
        $content = preg_replace('/\bPIN[:\s]+(\d{4})\b/i', 'PIN: ****', $content);
        
        // Mask 6-digit OTPs
        $content = preg_replace('/\bOTP[:\s]+(\d{6})\b/i', 'OTP: ******', $content);
        $content = preg_replace('/\bOTP\s+is\s+(\d{6})\b/i', 'OTP is ******', $content);
        $content = preg_replace('/\bYour\s+OTP[:\s]+(\d{6})\b/i', 'Your OTP: ******', $content);
        $content = preg_replace('/\b(\d{6})\b/', '******', $content); // Standalone 6-digit numbers
        
        // Mask password reset tokens/URLs
        $content = preg_replace('/reset[\/\?]token=([a-zA-Z0-9]{20,})/i', 'reset/token=****', $content);
        $content = preg_replace('/token=([a-zA-Z0-9]{20,})/i', 'token=****', $content);
        $content = preg_replace('/reset\/([a-zA-Z0-9]{20,})/i', 'reset/****', $content);
        
        // Mask long tokens (64+ characters)
        $content = preg_replace('/\b([a-zA-Z0-9]{64,})\b/', '****', $content);
        
        // Mask passwords in plain text
        $content = preg_replace('/\bpassword[:\s]+([^\s]+)/i', 'password: ****', $content);
        $content = preg_replace('/\bnew\s+password[:\s]+([^\s]+)/i', 'new password: ****', $content);
        
        return $content;
    }

    /**
     * Mask phone number (show only last 4 digits)
     */
    private static function maskPhone(string $phone): string
    {
        if (strlen($phone) <= 4) {
            return '****';
        }
        return '****' . substr($phone, -4);
    }
}

