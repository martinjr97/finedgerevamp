<?php

namespace App\PaymentPlatform\Support;

use App\Models\Channel;
use App\Models\EmployeeLoan;
use App\Models\FinancialInstitution;
use App\Models\Loan;
use App\Services\DisbursementDestinationService;
use Illuminate\Validation\ValidationException;

class CGrateIssuerNameResolver
{
    public function __construct(
        private readonly DisbursementDestinationService $destinationService,
    ) {}

    /**
     * @return array{customer_account: string, issuer_name: string, payment_method: string}
     */
    public function resolveForLoan(Loan $loan): array
    {
        $loan->loadMissing(['channel', 'disbursementFinancialInstitution']);

        $channel = $loan->channel;
        if (! $channel) {
            throw ValidationException::withMessages([
                'channel' => 'Loan has no disbursement channel configured.',
            ]);
        }

        $channelType = $loan->disbursement_channel_type
            ?: $this->destinationService->channelTypeFor($channel);

        if ($channelType === Channel::TYPE_CASH) {
            throw ValidationException::withMessages([
                'disbursement' => 'Cash disbursements cannot be processed via cGrate. Use manual disbursement.',
            ]);
        }

        if ($channelType === Channel::TYPE_BANK) {
            $accountNumber = trim((string) $loan->disbursement_account_number);
            if ($accountNumber === '') {
                throw ValidationException::withMessages([
                    'disbursement_account_number' => 'Bank account number is required for gateway disbursement.',
                ]);
            }

            $institution = $loan->disbursementFinancialInstitution;
            if (! $institution) {
                throw ValidationException::withMessages([
                    'disbursement_financial_institution_id' => 'Financial institution is required for bank disbursement.',
                ]);
            }

            return [
                'customer_account' => $accountNumber,
                'issuer_name' => (string) $institution->name,
                'payment_method' => 'bank',
            ];
        }

        $phone = trim((string) $loan->disbursement_phone_number);
        if ($phone === '') {
            throw ValidationException::withMessages([
                'disbursement_phone_number' => 'Mobile number is required for gateway disbursement.',
            ]);
        }

        $customerAccount = ZambiaMsisdnNormalizer::normalizeForCGrate(
            $phone,
            (string) config('cgrate.msisdn_format', 'local')
        );

        return [
            'customer_account' => $customerAccount,
            'issuer_name' => $this->resolveMobileIssuerName($channel),
            'payment_method' => 'mobile_money',
        ];
    }

    /**
     * @return array{customer_account: string, issuer_name: string, payment_method: string}
     */
    public function resolveForEmployeeLoan(EmployeeLoan $loan): array
    {
        $snapshot = $loan->disbursementSnapshot();
        if ($snapshot === []) {
            throw ValidationException::withMessages([
                'disbursement' => 'Employee loan has no payout destination configured.',
            ]);
        }

        $method = $snapshot['payout_method'] ?? $snapshot['account_type'] ?? 'bank';

        if ($method === 'bank') {
            $accountNumber = trim((string) ($snapshot['account_number'] ?? ''));
            if ($accountNumber === '') {
                throw ValidationException::withMessages([
                    'disbursement' => 'Bank account number is required for gateway disbursement.',
                ]);
            }

            $institutionName = null;
            if (! empty($snapshot['financial_institution_id'])) {
                $institutionName = FinancialInstitution::query()->find($snapshot['financial_institution_id'])?->name;
            }
            $institutionName ??= (string) ($snapshot['bank_name'] ?? '');

            if ($institutionName === '') {
                throw ValidationException::withMessages([
                    'disbursement' => 'Financial institution is required for bank disbursement.',
                ]);
            }

            return [
                'customer_account' => $accountNumber,
                'issuer_name' => $institutionName,
                'payment_method' => 'bank',
            ];
        }

        $phone = trim((string) ($snapshot['account_number'] ?? ''));
        if ($phone === '') {
            throw ValidationException::withMessages([
                'disbursement' => 'Mobile number is required for gateway disbursement.',
            ]);
        }

        $provider = strtoupper((string) ($snapshot['bank_name'] ?? ''));
        $issuerName = match (true) {
            str_contains($provider, 'MTN') => 'MTN',
            str_contains($provider, 'AIRTEL') => 'Airtel',
            str_contains($provider, 'ZAMTEL') => 'Zamtel',
            default => $this->guessIssuerFromChannelName((string) ($snapshot['bank_name'] ?? 'Mobile')),
        };

        return [
            'customer_account' => ZambiaMsisdnNormalizer::normalizeForCGrate(
                $phone,
                (string) config('cgrate.msisdn_format', 'local')
            ),
            'issuer_name' => $issuerName,
            'payment_method' => 'mobile_money',
        ];
    }

    public function resolveMobileIssuerName(Channel $channel): string
    {
        $map = (array) config('cgrate.issuer_name_map', []);
        $code = strtoupper((string) $channel->code);

        if (isset($map[$code])) {
            return (string) $map[$code];
        }

        return match ($code) {
            'MTN_MONEY' => 'MTN',
            'AIRTEL_MONEY' => 'Airtel',
            'ZAMTEL_MONEY' => 'Zamtel',
            default => $this->guessIssuerFromChannelName((string) $channel->name),
        };
    }

    private function guessIssuerFromChannelName(string $name): string
    {
        $upper = strtoupper($name);

        if (str_contains($upper, 'MTN')) {
            return 'MTN';
        }
        if (str_contains($upper, 'AIRTEL')) {
            return 'Airtel';
        }
        if (str_contains($upper, 'ZAMTEL')) {
            return 'Zamtel';
        }

        throw ValidationException::withMessages([
            'channel' => 'Cannot determine mobile money issuer for channel "'.$name.'". Configure cgrate.issuer_name_map.',
        ]);
    }
}
