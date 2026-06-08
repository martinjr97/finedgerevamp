<?php

namespace App\Services;

use App\Models\Admin;
use App\Models\AuditLog;
use App\Models\Channel;
use App\Models\Loan;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class LoanPaymentDetailsService
{
    public function __construct(
        private readonly CustomerNotificationService $customerNotificationService,
        private readonly DisbursementDestinationService $disbursementDestinationService,
    ) {
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>|null
     */
    public function stageChange(Loan $loan, array $input, ?Admin $admin, string $stage): ?array
    {
        $currentChannelId = (int) ($loan->channel_id ?? 0);
        $newChannelId = array_key_exists('channel_id', $input)
            ? (int) $input['channel_id']
            : $currentChannelId;

        $newChannel = Channel::query()->findOrFail($newChannelId);
        $payload = $this->buildDestinationPayload($newChannel, $loan, $input, $newChannelId);

        $currentFingerprint = $this->destinationFingerprint($loan);
        $normalized = $this->disbursementDestinationService->validateAndNormalize($payload);
        $newFingerprint = $this->fingerprintFromNormalized($normalized);

        if ($currentFingerprint === $newFingerprint) {
            return null;
        }

        $channelChanged = $newChannelId !== $currentChannelId;

        if ($channelChanged && (! $newChannel->is_active || ! $newChannel->can_disburse)) {
            throw ValidationException::withMessages([
                'channel_id' => 'Please select an active disbursement channel.',
            ]);
        }

        if (! $admin?->can('loans.update-payment-details')) {
            throw new AuthorizationException('You do not have permission to update loan payment details.');
        }

        $reason = trim((string) ($input['payment_change_reason'] ?? ''));

        if ($reason === '') {
            throw ValidationException::withMessages([
                'payment_change_reason' => 'A reason is required when changing payment details.',
            ]);
        }

        $oldChannel = $loan->relationLoaded('channel') ? $loan->channel : $loan->channel()->first();
        $changedFields = $this->changedDestinationFields($currentFingerprint, $newFingerprint);

        $change = [
            'stage' => $stage,
            'reason' => $reason,
            'changed_at' => now()->toIso8601String(),
            'changed_by_admin_id' => $admin?->id,
            'changed_by_admin_name' => $admin?->full_name ?? $admin?->email,
            'changed_fields' => $changedFields,
            'old' => [
                'channel_id' => $oldChannel?->id ?? ($currentChannelId > 0 ? $currentChannelId : null),
                'channel_name' => $oldChannel?->name,
                'channel_type' => $loan->disbursement_channel_type ?? $oldChannel?->type,
                'destination_summary' => $loan->disbursementDestinationSummary(),
                'account_number' => $loan->disbursement_phone_number ?: $loan->disbursement_account_number,
            ],
            'new' => [
                'channel_id' => $newChannel?->id ?? ($newChannelId > 0 ? $newChannelId : null),
                'channel_name' => $newChannel?->name,
                'channel_type' => $normalized['disbursement_channel_type'] ?? null,
                'destination_summary' => $this->summaryFromNormalized($normalized, $newChannel),
                'account_number' => $normalized['disbursement_phone_number'] ?? $normalized['disbursement_account_number'] ?? null,
            ],
        ];

        $metadata = is_array($loan->metadata) ? $loan->metadata : [];
        $trail = collect(data_get($metadata, 'payment_details_change_trail', []))
            ->filter(fn ($entry) => is_array($entry))
            ->values()
            ->all();

        $trail[] = $change;
        $metadata['payment_details_change_trail'] = $trail;
        $metadata['last_payment_details_change'] = $change;

        $loan->fill($this->disbursementDestinationService->loanAttributes($normalized));
        $loan->metadata = $metadata;
        $loan->setRelation('channel', $newChannel);

        return $change;
    }

    /**
     * @param  array<string, mixed>  $change
     */
    public function recordAudit(Loan $loan, array $change, ?Admin $admin): void
    {
        if (! Schema::hasTable('audit_logs')) {
            return;
        }

        $request = request();
        $actorName = $admin?->full_name ?? $admin?->name ?? $admin?->email;

        AuditLog::withoutEvents(function () use ($loan, $change, $admin, $actorName, $request): void {
            AuditLog::query()->create([
                'event' => 'payment_details_changed',
                'auditable_type' => Loan::class,
                'auditable_id' => (string) $loan->getKey(),
                'old_values' => data_get($change, 'old'),
                'new_values' => data_get($change, 'new'),
                'changed_fields' => data_get($change, 'changed_fields'),
                'actor_type' => $admin ? $admin::class : null,
                'actor_id' => $admin ? (string) $admin->getKey() : null,
                'actor_name' => $actorName,
                'actor_guard' => 'admin',
                'ip_address' => $request?->ip(),
                'user_agent' => $request?->userAgent(),
                'url' => $request?->fullUrl(),
                'http_method' => $request?->method(),
                'metadata' => [
                    'route_name' => $request?->route()?->getName(),
                    'stage' => data_get($change, 'stage'),
                    'reason' => data_get($change, 'reason'),
                ],
            ]);
        });
    }

    /**
     * @param  array<string, mixed>  $change
     */
    public function sendChangeNotification(Loan $loan, array $change): void
    {
        $this->customerNotificationService->sendLoanPaymentDetailsChanged($loan, $change);
    }

    /**
     * @return array<string, mixed>
     */
    private function destinationFingerprint(Loan $loan): array
    {
        return [
            'channel_id' => (int) ($loan->channel_id ?? 0),
            'disbursement_channel_type' => (string) ($loan->disbursement_channel_type ?? $loan->disbursementChannelType()),
            'disbursement_phone_number' => (string) ($loan->disbursement_phone_number ?? ''),
            'disbursement_financial_institution_id' => (int) ($loan->disbursement_financial_institution_id ?? 0),
            'disbursement_financial_institution_branch_id' => (int) ($loan->disbursement_financial_institution_branch_id ?? 0),
            'disbursement_account_holder_name' => (string) ($loan->disbursement_account_holder_name ?? ''),
            'disbursement_account_number' => (string) ($loan->disbursement_account_number ?? ''),
            'disbursement_notes' => (string) ($loan->disbursement_notes ?? ''),
        ];
    }

    /**
     * @param  array<string, mixed>  $normalized
     * @return array<string, mixed>
     */
    private function fingerprintFromNormalized(array $normalized): array
    {
        return [
            'channel_id' => (int) ($normalized['channel_id'] ?? 0),
            'disbursement_channel_type' => (string) ($normalized['disbursement_channel_type'] ?? ''),
            'disbursement_phone_number' => (string) ($normalized['disbursement_phone_number'] ?? ''),
            'disbursement_financial_institution_id' => (int) ($normalized['disbursement_financial_institution_id'] ?? 0),
            'disbursement_financial_institution_branch_id' => (int) ($normalized['disbursement_financial_institution_branch_id'] ?? 0),
            'disbursement_account_holder_name' => (string) ($normalized['disbursement_account_holder_name'] ?? ''),
            'disbursement_account_number' => (string) ($normalized['disbursement_account_number'] ?? ''),
            'disbursement_notes' => (string) ($normalized['disbursement_notes'] ?? ''),
        ];
    }

    /**
     * @param  array<string, mixed>  $before
     * @param  array<string, mixed>  $after
     * @return list<string>
     */
    private function changedDestinationFields(array $before, array $after): array
    {
        $fields = [];

        foreach ($after as $key => $value) {
            if (($before[$key] ?? null) !== $value) {
                $fields[] = $key;
            }
        }

        return $fields;
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    private function buildDestinationPayload(Channel $channel, Loan $loan, array $input, int $channelId): array
    {
        $type = $this->disbursementDestinationService->channelTypeFor($channel);
        $payload = ['channel_id' => $channelId];

        if ($type === Channel::TYPE_MOBILE_WALLET) {
            $payload['disbursement_phone_number'] = $input['disbursement_phone_number'] ?? $loan->disbursement_phone_number;
        } elseif ($type === Channel::TYPE_BANK) {
            $payload['disbursement_financial_institution_id'] = $input['disbursement_financial_institution_id'] ?? $loan->disbursement_financial_institution_id;
            $payload['disbursement_financial_institution_branch_id'] = $input['disbursement_financial_institution_branch_id'] ?? $loan->disbursement_financial_institution_branch_id;
            $payload['disbursement_account_holder_name'] = $input['disbursement_account_holder_name'] ?? $loan->disbursement_account_holder_name;
            $payload['disbursement_account_number'] = $input['disbursement_account_number'] ?? $loan->disbursement_account_number;
        } else {
            $payload['disbursement_notes'] = $input['disbursement_notes'] ?? $loan->disbursement_notes;
        }

        return $payload;
    }

    /**
     * @param  array<string, mixed>  $normalized
     */
    private function summaryFromNormalized(array $normalized, ?Channel $channel): string
    {
        $snapshot = $normalized['disbursement_destination_snapshot'] ?? [];
        $type = $normalized['disbursement_channel_type'] ?? '';

        return match ($type) {
            Channel::TYPE_BANK => implode(' · ', array_filter([
                $snapshot['financial_institution_name'] ?? null,
                $snapshot['branch_name'] ?? null,
                $snapshot['account_holder_name'] ?? null,
                $snapshot['masked_account_number'] ?? null,
            ])),
            Channel::TYPE_CASH => ($normalized['disbursement_notes'] ?? null)
                ? ($channel?->name ?? 'Cash').' · '.$normalized['disbursement_notes']
                : ($channel?->name ?? 'Cash'),
            default => ($normalized['disbursement_phone_number'] ?? null)
                ? ($channel?->name ?? 'Mobile Wallet').' · '.$normalized['disbursement_phone_number']
                : ($channel?->name ?? 'Mobile Wallet'),
        };
    }
}
