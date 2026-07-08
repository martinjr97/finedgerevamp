<?php

namespace App\Sms\DTOs;

use App\Sms\Enums\SmsCategory;
use Illuminate\Database\Eloquent\Model;

final class SmsMessage
{
    /**
     * @param  array<string, mixed>  $metadata
     */
    public function __construct(
        public readonly string $phone,
        public readonly string $body,
        public readonly SmsCategory $category,
        public readonly string $messageType,
        public readonly ?string $recipientType = null,
        public readonly ?int $recipientId = null,
        public readonly ?int $customerId = null,
        public readonly ?int $adminId = null,
        public readonly ?int $loanId = null,
        public readonly array $metadata = [],
        public readonly bool $forceSend = false,
    ) {
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        $category = $data['category'] ?? SmsCategory::General;
        if (is_string($category)) {
            $category = SmsCategory::from($category);
        }

        $recipientType = $data['recipient_type'] ?? null;
        $recipientId = $data['recipient_id'] ?? null;

        if ($recipientType === null && isset($data['recipient']) && $data['recipient'] instanceof Model) {
            $recipientType = $data['recipient']->getMorphClass();
            $recipientId = (int) $data['recipient']->getKey();
        }

        return new self(
            phone: (string) ($data['phone'] ?? ''),
            body: (string) ($data['body'] ?? $data['message'] ?? ''),
            category: $category,
            messageType: (string) ($data['message_type'] ?? $data['messageType'] ?? 'general'),
            recipientType: $recipientType,
            recipientId: $recipientId !== null ? (int) $recipientId : null,
            customerId: isset($data['customer_id']) ? (int) $data['customer_id'] : null,
            adminId: isset($data['admin_id']) ? (int) $data['admin_id'] : null,
            loanId: isset($data['loan_id']) ? (int) $data['loan_id'] : null,
            metadata: is_array($data['metadata'] ?? null) ? $data['metadata'] : [],
            forceSend: (bool) ($data['force_send'] ?? $data['forceSend'] ?? false),
        );
    }
}
