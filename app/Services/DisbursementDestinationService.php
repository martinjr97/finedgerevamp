<?php

namespace App\Services;

use App\Models\Channel;
use App\Models\FinancialInstitution;
use App\Models\Loan;
use App\Models\FinancialInstitutionBranch;
use App\Support\ZambianPhoneRules;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class DisbursementDestinationService
{
    public function channelTypeFor(Channel $channel): string
    {
        $type = $channel->type;

        if ($type && in_array($type, Channel::TYPES, true)) {
            return $type;
        }

        return Channel::TYPE_MOBILE_WALLET;
    }

    /**
     * @return array<string, mixed>
     */
    public function rulesForChannel(Channel $channel, ?int $financialInstitutionId = null): array
    {
        return match ($this->channelTypeFor($channel)) {
            Channel::TYPE_BANK => $this->bankDestinationRules($financialInstitutionId),
            Channel::TYPE_CASH => $this->cashDestinationRules(),
            default => $this->mobileWalletDestinationRules(),
        };
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function validateAndNormalize(array $payload): array
    {
        $channel = $this->resolveChannel($payload['channel_id'] ?? null);
        $validated = $this->validate($payload);

        return $this->normalize($validated, $channel);
    }

    /**
     * @return array<string, mixed>
     */
    public function payloadFromLoan(Loan $loan): array
    {
        return [
            'channel_id' => $loan->channel_id,
            'disbursement_phone_number' => $loan->disbursement_phone_number,
            'disbursement_financial_institution_id' => $loan->disbursement_financial_institution_id,
            'disbursement_financial_institution_branch_id' => $loan->disbursement_financial_institution_branch_id,
            'disbursement_account_holder_name' => $loan->disbursement_account_holder_name,
            'disbursement_account_number' => $loan->disbursement_account_number,
            'disbursement_notes' => $loan->disbursement_notes,
        ];
    }

    /**
     * @param  array<string, mixed>  $normalized
     * @return array<string, mixed>
     */
    public function loanAttributes(array $normalized): array
    {
        return [
            'channel_id' => $normalized['channel_id'] ?? null,
            'disbursement_channel_type' => $normalized['disbursement_channel_type'] ?? null,
            'disbursement_phone_number' => $normalized['disbursement_phone_number'] ?? null,
            'disbursement_financial_institution_id' => $normalized['disbursement_financial_institution_id'] ?? null,
            'disbursement_financial_institution_branch_id' => $normalized['disbursement_financial_institution_branch_id'] ?? null,
            'disbursement_account_holder_name' => $normalized['disbursement_account_holder_name'] ?? null,
            'disbursement_account_number' => $normalized['disbursement_account_number'] ?? null,
            'disbursement_destination_snapshot' => $normalized['disbursement_destination_snapshot'] ?? null,
            'disbursement_notes' => $normalized['disbursement_notes'] ?? null,
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function validate(array $payload): array
    {
        $channel = $this->resolveChannel($payload['channel_id'] ?? null);

        $institutionId = isset($payload['disbursement_financial_institution_id'])
            ? (int) $payload['disbursement_financial_institution_id']
            : null;

        $rules = array_merge([
            'channel_id' => ['required', 'integer', Rule::exists('channels', 'id')->whereNull('deleted_at')],
        ], $this->rulesForChannel($channel, $institutionId));

        if ($this->channelTypeFor($channel) === Channel::TYPE_BANK) {
            $rules['disbursement_financial_institution_branch_id'][] = $this->branchBelongsToInstitutionRule($institutionId);
        }

        $validator = Validator::make(
            $payload,
            $rules,
            ZambianPhoneRules::messages(),
            ZambianPhoneRules::attributes()
        );

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        return $validator->validated();
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    public function normalize(array $validated, Channel $channel): array
    {
        $type = $this->channelTypeFor($channel);

        $normalized = match ($type) {
            Channel::TYPE_BANK => $this->normalizeBankFields($validated),
            Channel::TYPE_CASH => $this->normalizeCashFields($validated),
            default => $this->normalizeMobileWalletFields($validated),
        };

        $normalized['channel_id'] = $channel->id;
        $normalized['disbursement_channel_type'] = $type;
        $normalized['disbursement_destination_snapshot'] = $this->snapshot($normalized, $channel);

        return $normalized;
    }

    /**
     * @param  array<string, mixed>  $normalized
     * @return array<string, mixed>
     */
    public function snapshot(array $normalized, Channel $channel): array
    {
        $type = $this->channelTypeFor($channel);
        $base = [
            'channel_name' => $channel->name,
            'channel_type' => $type,
            'channel_code' => $channel->code,
        ];

        return match ($type) {
            Channel::TYPE_BANK => array_merge($base, $this->bankSnapshot($normalized)),
            Channel::TYPE_CASH => array_merge($base, $this->cashSnapshot($normalized)),
            default => array_merge($base, $this->mobileWalletSnapshot($normalized)),
        };
    }

    public static function maskAccountNumber(?string $accountNumber): ?string
    {
        if ($accountNumber === null || $accountNumber === '') {
            return null;
        }

        $digits = preg_replace('/\s+/', '', $accountNumber) ?? '';
        if (strlen($digits) <= 4) {
            return '******'.$digits;
        }

        return '******'.substr($digits, -4);
    }

    /**
     * @return array<string, mixed>
     */
    private function mobileWalletDestinationRules(): array
    {
        return [
            'disbursement_phone_number' => ZambianPhoneRules::required(),
            'disbursement_financial_institution_id' => ['prohibited'],
            'disbursement_financial_institution_branch_id' => ['prohibited'],
            'disbursement_account_holder_name' => ['prohibited'],
            'disbursement_account_number' => ['prohibited'],
            'disbursement_notes' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function bankDestinationRules(?int $financialInstitutionId): array
    {
        $branchRule = Rule::exists('financial_institution_branches', 'id')->whereNull('deleted_at');

        if ($financialInstitutionId) {
            $branchRule = $branchRule->where('financial_institution_id', $financialInstitutionId);
        }

        return [
            'disbursement_phone_number' => ['prohibited'],
            'disbursement_financial_institution_id' => [
                'required',
                'integer',
                Rule::exists('financial_institutions', 'id')->whereNull('deleted_at'),
            ],
            'disbursement_financial_institution_branch_id' => [
                'required',
                'integer',
                $branchRule,
            ],
            'disbursement_account_holder_name' => ['required', 'string', 'max:255'],
            'disbursement_account_number' => ['required', 'string', 'max:50'],
            'disbursement_notes' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function cashDestinationRules(): array
    {
        return [
            'disbursement_phone_number' => ['prohibited'],
            'disbursement_financial_institution_id' => ['prohibited'],
            'disbursement_financial_institution_branch_id' => ['prohibited'],
            'disbursement_account_holder_name' => ['prohibited'],
            'disbursement_account_number' => ['prohibited'],
            'disbursement_notes' => ['nullable', 'string', 'max:1000'],
        ];
    }

    private function branchBelongsToInstitutionRule(?int $financialInstitutionId): \Closure
    {
        return function (string $attribute, mixed $value, \Closure $fail) use ($financialInstitutionId): void {
            if (! $financialInstitutionId) {
                return;
            }

            $branch = FinancialInstitutionBranch::query()->find($value);

            if (! $branch || (int) $branch->financial_institution_id !== $financialInstitutionId) {
                $fail('The selected branch does not belong to the selected financial institution.');
            }
        };
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    private function normalizeMobileWalletFields(array $validated): array
    {
        return [
            'disbursement_phone_number' => $this->normalizePhone($validated['disbursement_phone_number'] ?? null),
            'disbursement_financial_institution_id' => null,
            'disbursement_financial_institution_branch_id' => null,
            'disbursement_account_holder_name' => null,
            'disbursement_account_number' => null,
            'disbursement_notes' => $validated['disbursement_notes'] ?? null,
        ];
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    private function normalizeBankFields(array $validated): array
    {
        return [
            'disbursement_phone_number' => null,
            'disbursement_financial_institution_id' => (int) $validated['disbursement_financial_institution_id'],
            'disbursement_financial_institution_branch_id' => (int) $validated['disbursement_financial_institution_branch_id'],
            'disbursement_account_holder_name' => trim((string) $validated['disbursement_account_holder_name']),
            'disbursement_account_number' => trim((string) $validated['disbursement_account_number']),
            'disbursement_notes' => $validated['disbursement_notes'] ?? null,
        ];
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    private function normalizeCashFields(array $validated): array
    {
        return [
            'disbursement_phone_number' => null,
            'disbursement_financial_institution_id' => null,
            'disbursement_financial_institution_branch_id' => null,
            'disbursement_account_holder_name' => null,
            'disbursement_account_number' => null,
            'disbursement_notes' => $validated['disbursement_notes'] ?? null,
        ];
    }

    /**
     * @param  array<string, mixed>  $normalized
     * @return array<string, mixed>
     */
    private function mobileWalletSnapshot(array $normalized): array
    {
        return [
            'disbursement_phone_number' => $normalized['disbursement_phone_number'] ?? null,
        ];
    }

    /**
     * @param  array<string, mixed>  $normalized
     * @return array<string, mixed>
     */
    private function bankSnapshot(array $normalized): array
    {
        $institution = FinancialInstitution::query()->find($normalized['disbursement_financial_institution_id'] ?? null);
        $branch = FinancialInstitutionBranch::query()->find($normalized['disbursement_financial_institution_branch_id'] ?? null);
        $accountNumber = $normalized['disbursement_account_number'] ?? null;

        return [
            'financial_institution_name' => $institution?->name,
            'financial_institution_code' => $institution?->code,
            'branch_name' => $branch?->name,
            'branch_code' => $branch?->code,
            'account_holder_name' => $normalized['disbursement_account_holder_name'] ?? null,
            'masked_account_number' => self::maskAccountNumber(is_string($accountNumber) ? $accountNumber : null),
        ];
    }

    /**
     * @param  array<string, mixed>  $normalized
     * @return array<string, mixed>
     */
    private function cashSnapshot(array $normalized): array
    {
        return [
            'notes' => $normalized['disbursement_notes'] ?? null,
        ];
    }

    private function resolveChannel(mixed $channelId): Channel
    {
        if (! $channelId) {
            throw ValidationException::withMessages([
                'channel_id' => 'Please select a disbursement channel.',
            ]);
        }

        $channel = Channel::query()->find($channelId);

        if (! $channel) {
            throw ValidationException::withMessages([
                'channel_id' => 'The selected disbursement channel is invalid.',
            ]);
        }

        return $channel;
    }

    private function normalizePhone(mixed $phone): ?string
    {
        if ($phone === null || $phone === '') {
            return null;
        }

        return trim((string) $phone);
    }
}
