<?php

namespace App\Services;

use App\Models\Channel;
use App\Models\Customer;
use App\Models\CustomerPaymentDetail;
use App\Models\FinancialInstitution;
use App\Models\FinancialInstitutionBranch;
use App\Models\WalletProvider;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class CustomerPaymentDetailPrefillService
{
    /**
     * Map stored customer payment details to loan-application disbursement fields.
     *
     * @param  Collection<int, Channel>  $disbursementChannels
     * @return array<string, mixed>|null
     */
    public function disbursementDefaults(Customer $customer, Collection $disbursementChannels): ?array
    {
        $detail = $customer->relationLoaded('paymentDetail')
            ? $customer->paymentDetail
            : $customer->paymentDetail()->first();

        if (! $detail) {
            return null;
        }

        return match ($detail->method_type) {
            'wallet' => $this->walletDefaults($detail, $disbursementChannels),
            'bank' => $this->bankDefaults($detail, $disbursementChannels),
            default => null,
        };
    }

    /**
     * @param  array<string, mixed>  $normalized  Output from DisbursementDestinationService::validateAndNormalize
     */
    public function syncFromNormalizedDestination(Customer $customer, array $normalized): void
    {
        $channelType = (string) ($normalized['disbursement_channel_type'] ?? '');

        if ($channelType === Channel::TYPE_MOBILE_WALLET) {
            $phone = Str::upper(trim((string) ($normalized['disbursement_phone_number'] ?? '')));
            $channelName = (string) data_get($normalized, 'disbursement_destination_snapshot.channel_name', '');
            $provider = WalletProvider::query()
                ->active()
                ->when($channelName !== '', function ($query) use ($channelName): void {
                    $query->where('name', 'like', '%'.Str::before($channelName, ' ').'%');
                })
                ->orderBy('name')
                ->first();

            CustomerPaymentDetail::updateOrCreate(
                ['customer_id' => $customer->id],
                [
                    'method_type' => 'wallet',
                    'wallet_provider_id' => $provider?->id,
                    'wallet_provider' => $provider ? Str::upper($provider->name) : ($channelName !== '' ? Str::upper($channelName) : null),
                    'wallet_number' => $phone !== '' ? $phone : null,
                    'bank_financial_institution_id' => null,
                    'bank_financial_institution_branch_id' => null,
                    'bank_name' => null,
                    'bank_branch' => null,
                    'account_name' => null,
                    'account_number' => null,
                ]
            );

            return;
        }

        if ($channelType === Channel::TYPE_BANK) {
            $institutionId = (int) ($normalized['disbursement_financial_institution_id'] ?? 0);
            $branchId = (int) ($normalized['disbursement_financial_institution_branch_id'] ?? 0);
            $institution = $institutionId > 0 ? FinancialInstitution::query()->find($institutionId) : null;
            $branch = $branchId > 0 ? FinancialInstitutionBranch::query()->find($branchId) : null;

            CustomerPaymentDetail::updateOrCreate(
                ['customer_id' => $customer->id],
                [
                    'method_type' => 'bank',
                    'bank_financial_institution_id' => $institution?->id,
                    'bank_financial_institution_branch_id' => $branch?->id,
                    'bank_name' => $institution ? Str::upper($institution->name) : null,
                    'bank_branch' => $branch ? Str::upper($branch->name) : null,
                    'account_name' => Str::upper(trim((string) ($normalized['disbursement_account_holder_name'] ?? ''))) ?: null,
                    'account_number' => Str::upper(trim((string) ($normalized['disbursement_account_number'] ?? ''))) ?: null,
                    'wallet_provider_id' => null,
                    'wallet_provider' => null,
                    'wallet_number' => null,
                ]
            );
        }
    }

    /**
     * @param  Collection<int, Channel>  $disbursementChannels
     * @return array<string, mixed>|null
     */
    private function walletDefaults(CustomerPaymentDetail $detail, Collection $disbursementChannels): ?array
    {
        $channel = $this->resolveWalletChannel($detail, $disbursementChannels);
        if (! $channel) {
            return null;
        }

        $phone = $detail->wallet_number ?: null;

        return array_filter([
            'channel_id' => $channel->id,
            'disbursement_phone_number' => $phone,
        ], fn ($value) => filled($value));
    }

    /**
     * @param  Collection<int, Channel>  $disbursementChannels
     * @return array<string, mixed>|null
     */
    private function bankDefaults(CustomerPaymentDetail $detail, Collection $disbursementChannels): ?array
    {
        $channel = $disbursementChannels->first(
            fn (Channel $item): bool => ($item->type ?? null) === Channel::TYPE_BANK
        );

        if (! $channel) {
            return null;
        }

        $institutionId = $detail->bank_financial_institution_id;
        $branchId = $detail->bank_financial_institution_branch_id;

        if (! $institutionId && filled($detail->bank_name)) {
            $institutionId = FinancialInstitution::query()
                ->active()
                ->where('name', (string) $detail->bank_name)
                ->value('id');
        }

        if (! $branchId && $institutionId && filled($detail->bank_branch)) {
            $branchId = FinancialInstitutionBranch::query()
                ->active()
                ->where('financial_institution_id', (int) $institutionId)
                ->where('name', (string) $detail->bank_branch)
                ->value('id');
        }

        if (! $institutionId || ! $branchId) {
            return null;
        }

        return array_filter([
            'channel_id' => $channel->id,
            'disbursement_financial_institution_id' => $institutionId,
            'disbursement_financial_institution_branch_id' => $branchId,
            'disbursement_account_holder_name' => $detail->account_name,
            'disbursement_account_number' => $detail->account_number,
        ], fn ($value) => filled($value));
    }

    /**
     * @param  Collection<int, Channel>  $disbursementChannels
     */
    private function resolveWalletChannel(CustomerPaymentDetail $detail, Collection $disbursementChannels): ?Channel
    {
        $walletChannels = $disbursementChannels->filter(
            fn (Channel $item): bool => ($item->type ?? null) === Channel::TYPE_MOBILE_WALLET
        );

        if ($walletChannels->isEmpty()) {
            return null;
        }

        $providerName = Str::upper(trim((string) ($detail->wallet_provider ?? '')));

        if ($providerName !== '') {
            $needle = Str::before($providerName, ' ');

            $matched = $walletChannels->first(function (Channel $channel) use ($needle, $providerName): bool {
                $name = Str::upper($channel->name);
                $code = Str::upper((string) $channel->code);

                return Str::contains($name, $needle)
                    || Str::contains($code, $needle)
                    || Str::contains($providerName, Str::before($name, ' '));
            });

            if ($matched) {
                return $matched;
            }
        }

        return $walletChannels->first();
    }
}
