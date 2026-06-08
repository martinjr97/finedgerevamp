@php
    use App\Models\Channel;
    use App\Services\DisbursementDestinationService;

    $channel = $channel ?? null;
    $loan = $loan ?? null;
    $loanData = $loanData ?? null;

    if ($loan) {
        $destinationType = $loan->disbursementChannelType();
        $destinationLabel = match ($destinationType) {
            Channel::TYPE_BANK => 'Bank Transfer',
            Channel::TYPE_CASH => 'Cash',
            default => 'Mobile Money',
        };
        $destinationSummary = $loan->disbursementDestinationSummary();
        $channelName = $loan->channel?->name ?? data_get($loan->disbursement_destination_snapshot, 'channel_name', '—');
        $maskedAccount = data_get($loan->disbursement_destination_snapshot, 'masked_account_number')
            ?? DisbursementDestinationService::maskAccountNumber($loan->disbursement_account_number);
    } else {
        $channelType = $channel?->type ?? ($loanData['disbursement_channel_type'] ?? Channel::TYPE_MOBILE_WALLET);
        $destinationType = $loanData['disbursement_channel_type'] ?? $channelType;
        $destinationLabel = match ($destinationType) {
            Channel::TYPE_BANK => 'Bank Transfer',
            Channel::TYPE_CASH => 'Cash',
            default => 'Mobile Money',
        };
        $channelName = $channel?->name ?? '—';
        $snapshot = $loanData['disbursement_destination_snapshot'] ?? null;
        $maskedAccount = null;

        if (is_array($snapshot) && ! empty($snapshot)) {
            $destinationSummary = match ($destinationType) {
                Channel::TYPE_BANK => implode(' · ', array_filter([
                    $snapshot['financial_institution_name'] ?? null,
                    $snapshot['branch_name'] ?? null,
                    $snapshot['account_holder_name'] ?? null,
                    $snapshot['masked_account_number'] ?? null,
                ])),
                Channel::TYPE_CASH => ($snapshot['notes'] ?? null) ? $channelName.' · '.$snapshot['notes'] : $channelName,
                default => ($snapshot['disbursement_phone_number'] ?? $loanData['disbursement_phone_number'] ?? null)
                    ? $channelName.' · '.($snapshot['disbursement_phone_number'] ?? $loanData['disbursement_phone_number'])
                    : $channelName,
            };
            $maskedAccount = $snapshot['masked_account_number'] ?? null;
        } else {
            $destinationSummary = match ($destinationType) {
                Channel::TYPE_BANK => implode(' · ', array_filter([
                    $loanData['disbursement_account_holder_name'] ?? null,
                    isset($loanData['disbursement_account_number'])
                        ? DisbursementDestinationService::maskAccountNumber((string) $loanData['disbursement_account_number'])
                        : null,
                ])) ?: $channelName,
                Channel::TYPE_CASH => ($loanData['disbursement_notes'] ?? null) ? $channelName.' · '.$loanData['disbursement_notes'] : $channelName,
                default => ($loanData['disbursement_phone_number'] ?? null)
                    ? $channelName.' · '.$loanData['disbursement_phone_number']
                    : $channelName,
            };
            if ($destinationType === Channel::TYPE_BANK && isset($loanData['disbursement_account_number'])) {
                $maskedAccount = DisbursementDestinationService::maskAccountNumber((string) $loanData['disbursement_account_number']);
            }
        }
    }
@endphp

<div class="space-y-3 text-sm">
    <div class="grid gap-4 md:grid-cols-2">
        <div>
            <p class="text-xs uppercase tracking-wide text-gray-500 dark:text-gray-400 mb-1">Disbursement channel</p>
            <p class="font-semibold text-gray-900 dark:text-white">{{ $channelName }}</p>
        </div>
        <div>
            <p class="text-xs uppercase tracking-wide text-gray-500 dark:text-gray-400 mb-1">Method</p>
            <p class="font-semibold text-gray-900 dark:text-white">{{ $destinationLabel }}</p>
        </div>
    </div>
    <div>
        <p class="text-xs uppercase tracking-wide text-gray-500 dark:text-gray-400 mb-1">Destination</p>
        <p class="font-semibold text-gray-900 dark:text-white">{{ $destinationSummary ?: '—' }}</p>
    </div>
    @if(($loan && $loan->hasBankDestination()) || (($destinationType ?? null) === Channel::TYPE_BANK && ! empty($maskedAccount)))
        <div>
            <p class="text-xs uppercase tracking-wide text-gray-500 dark:text-gray-400 mb-1">Account number</p>
            <p class="font-semibold text-gray-900 dark:text-white">{{ $maskedAccount ?? '—' }}</p>
        </div>
    @endif
</div>
