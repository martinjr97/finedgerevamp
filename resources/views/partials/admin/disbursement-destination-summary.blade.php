@php
    use App\Models\Channel;
    use App\Services\DisbursementDestinationService;

    $channel = $channel ?? null;
    $loan = $loan ?? null;
    $loanData = $loanData ?? null;

    if ($loan) {
        $destinationType = $loan->disbursementChannelType();
        $destinationLabel = Channel::typeOptions()[$destinationType] ?? ucfirst(str_replace('_', ' ', $destinationType));
        $destinationSummary = $loan->disbursementDestinationSummary();
        $channelName = $loan->channel?->name ?? data_get($loan->disbursement_destination_snapshot, 'channel_name', '—');
    } else {
        $channelType = $channel?->type ?? Channel::TYPE_MOBILE_WALLET;
        $destinationType = $loanData['disbursement_channel_type'] ?? $channelType;
        $destinationLabel = Channel::typeOptions()[$destinationType] ?? ucfirst(str_replace('_', ' ', (string) $destinationType));
        $channelName = $channel?->name ?? '—';
        $snapshot = $loanData['disbursement_destination_snapshot'] ?? null;

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
        }
    }
@endphp

<div class="space-y-3 text-sm">
    <div>
        <p class="text-xs uppercase tracking-wide text-slate-400 mb-1">Disbursement Channel</p>
        <p class="text-base font-semibold text-white">{{ $channelName }}</p>
    </div>
    <div>
        <p class="text-xs uppercase tracking-wide text-slate-400 mb-1">Destination Type</p>
        <p class="text-base font-semibold text-white">{{ $destinationLabel }}</p>
    </div>
    <div>
        <p class="text-xs uppercase tracking-wide text-slate-400 mb-1">Destination Summary</p>
        <p class="text-base font-semibold text-white">{{ $destinationSummary ?: '—' }}</p>
    </div>
    @if($loan && $loan->hasBankDestination())
        <div class="grid gap-3 md:grid-cols-2">
            <div>
                <p class="text-xs uppercase tracking-wide text-slate-400 mb-1">Account holder</p>
                <p class="font-medium text-white">{{ $loan->disbursement_account_holder_name ?? data_get($loan->disbursement_destination_snapshot, 'account_holder_name', '—') }}</p>
            </div>
            <div>
                <p class="text-xs uppercase tracking-wide text-slate-400 mb-1">Account number</p>
                <p class="font-medium text-white">{{ data_get($loan->disbursement_destination_snapshot, 'masked_account_number') ?? \App\Services\DisbursementDestinationService::maskAccountNumber($loan->disbursement_account_number) ?? '—' }}</p>
            </div>
        </div>
    @elseif($loan && $loan->hasMobileWalletDestination())
        <div>
            <p class="text-xs uppercase tracking-wide text-slate-400 mb-1">Mobile number</p>
            <p class="font-medium text-white">{{ $loan->disbursement_phone_number ?? '—' }}</p>
        </div>
    @elseif($loan && $loan->hasCashDestination() && $loan->disbursement_notes)
        <div>
            <p class="text-xs uppercase tracking-wide text-slate-400 mb-1">Notes</p>
            <p class="font-medium text-white">{{ $loan->disbursement_notes }}</p>
        </div>
    @endif
</div>
