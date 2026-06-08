<?php

namespace App\Http\Controllers\Concerns;

use App\Services\DisbursementDestinationService;
use Illuminate\Http\Request;

trait ResolvesDisbursementDestination
{
    /**
     * @var list<string>
     */
    protected array $disbursementDestinationSessionKeys = [
        'disbursement_channel_type',
        'disbursement_phone_number',
        'disbursement_financial_institution_id',
        'disbursement_financial_institution_branch_id',
        'disbursement_account_holder_name',
        'disbursement_account_number',
        'disbursement_destination_snapshot',
        'disbursement_notes',
        'destination_validated',
    ];
    protected function disbursementDestinationService(): DisbursementDestinationService
    {
        return app(DisbursementDestinationService::class);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    protected function normalizeDisbursementDestination(array $payload): array
    {
        return $this->disbursementDestinationService()->validateAndNormalize($payload);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    protected function loanDestinationAttributes(array $payload): array
    {
        return $this->disbursementDestinationService()->loanAttributes(
            $this->normalizeDisbursementDestination($payload)
        );
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function destinationPayloadFromArray(array $data): array
    {
        return [
            'channel_id' => $data['channel_id'] ?? null,
            'disbursement_phone_number' => $data['disbursement_phone_number'] ?? null,
            'disbursement_financial_institution_id' => $data['disbursement_financial_institution_id'] ?? null,
            'disbursement_financial_institution_branch_id' => $data['disbursement_financial_institution_branch_id'] ?? null,
            'disbursement_account_holder_name' => $data['disbursement_account_holder_name'] ?? null,
            'disbursement_account_number' => $data['disbursement_account_number'] ?? null,
            'disbursement_notes' => $data['disbursement_notes'] ?? null,
        ];
    }

    /**
     * @param  array<string, mixed>  $loanData
     * @return array<string, mixed>
     */
    protected function destinationAttributesFromLoanData(array $loanData): array
    {
        $attributes = $this->loanDestinationAttributes(
            $this->destinationPayloadFromArray($loanData)
        );

        if (! empty($loanData['loan_purpose_id'])) {
            $attributes['loan_purpose_id'] = $loanData['loan_purpose_id'];
        }

        return $attributes;
    }

    /**
     * @return array<string, mixed>
     */
    protected function destinationPayloadFromLoanApplicationSession(): array
    {
        if (session('loan_application.destination_validated')) {
            return $this->destinationPayloadFromArray([
                'channel_id' => session('loan_application.channel_id'),
                'disbursement_phone_number' => session('loan_application.disbursement_phone_number'),
                'disbursement_financial_institution_id' => session('loan_application.disbursement_financial_institution_id'),
                'disbursement_financial_institution_branch_id' => session('loan_application.disbursement_financial_institution_branch_id'),
                'disbursement_account_holder_name' => session('loan_application.disbursement_account_holder_name'),
                'disbursement_account_number' => session('loan_application.disbursement_account_number'),
                'disbursement_notes' => session('loan_application.disbursement_notes'),
            ]);
        }

        if (session('loan_application.channel_id') && session('loan_application.phone_number')) {
            return [
                'channel_id' => session('loan_application.channel_id'),
                'disbursement_phone_number' => session('loan_application.phone_number'),
            ];
        }

        return [
            'channel_id' => session('loan_application.channel_id'),
        ];
    }

    protected function hasLoanApplicationDestinationInSession(): bool
    {
        if (session('loan_application.destination_validated')) {
            return (bool) session('loan_application.channel_id');
        }

        return session('loan_application.channel_id')
            && session('loan_application.phone_number');
    }

    /**
     * @param  array<string, mixed>  $normalized
     */
    protected function mergeLoanApplicationDestinationSession(array $normalized): void
    {
        session([
            'loan_application.destination_validated' => true,
            'loan_application.disbursement_channel_type' => $normalized['disbursement_channel_type'] ?? null,
            'loan_application.disbursement_phone_number' => $normalized['disbursement_phone_number'] ?? null,
            'loan_application.disbursement_financial_institution_id' => $normalized['disbursement_financial_institution_id'] ?? null,
            'loan_application.disbursement_financial_institution_branch_id' => $normalized['disbursement_financial_institution_branch_id'] ?? null,
            'loan_application.disbursement_account_holder_name' => $normalized['disbursement_account_holder_name'] ?? null,
            'loan_application.disbursement_account_number' => $normalized['disbursement_account_number'] ?? null,
            'loan_application.disbursement_notes' => $normalized['disbursement_notes'] ?? null,
            'loan_application.disbursement_destination_snapshot' => $normalized['disbursement_destination_snapshot'] ?? null,
            'loan_application.phone_number' => $normalized['disbursement_phone_number'] ?? null,
        ]);
    }

    protected function forgetLoanApplicationDestinationSession(): void
    {
        session()->forget([
            'loan_application.destination_validated',
            'loan_application.disbursement_channel_type',
            'loan_application.disbursement_phone_number',
            'loan_application.disbursement_financial_institution_id',
            'loan_application.disbursement_financial_institution_branch_id',
            'loan_application.disbursement_account_holder_name',
            'loan_application.disbursement_account_number',
            'loan_application.disbursement_notes',
            'loan_application.disbursement_destination_snapshot',
            'loan_application.phone_number',
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    protected function destinationPayloadFromRequest(Request $request, ?int $channelId = null): array
    {
        $payload = $this->destinationPayloadFromArray(array_merge(
            ['channel_id' => $channelId ?? $request->input('channel_id')],
            $request->only([
                'disbursement_phone_number',
                'disbursement_financial_institution_id',
                'disbursement_financial_institution_branch_id',
                'disbursement_account_holder_name',
                'disbursement_account_number',
                'disbursement_notes',
            ])
        ));

        if ($request->input('use_profile_phone') === '1') {
            $customer = auth('customer')->user();
            if ($customer?->phone) {
                $payload['disbursement_phone_number'] = $customer->phone;
            }
        }

        return $payload;
    }

    /**
     * @return array<string, mixed>
     */
    protected function loanApplicationDestinationDataForView(): array
    {
        return [
            'disbursement_channel_type' => session('loan_application.disbursement_channel_type'),
            'disbursement_phone_number' => session('loan_application.disbursement_phone_number')
                ?? session('loan_application.phone_number'),
            'disbursement_financial_institution_id' => session('loan_application.disbursement_financial_institution_id'),
            'disbursement_financial_institution_branch_id' => session('loan_application.disbursement_financial_institution_branch_id'),
            'disbursement_account_holder_name' => session('loan_application.disbursement_account_holder_name'),
            'disbursement_account_number' => session('loan_application.disbursement_account_number'),
            'disbursement_notes' => session('loan_application.disbursement_notes'),
            'disbursement_destination_snapshot' => session('loan_application.disbursement_destination_snapshot'),
        ];
    }
}
