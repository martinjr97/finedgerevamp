<?php

namespace App\Support;

use App\Models\PaymentGateway;
use App\Models\PaymentGatewayDestinationMapping;
use Carbon\CarbonInterface;

class PaymentGatewayDestinationMappingAdminUi
{
    /**
     * @return array{emoji: string, label: string, class: string}
     */
    public static function statusBadge(string $status): array
    {
        return match ($status) {
            'active' => static::badge('🟢', 'Active', 'bg-emerald-100 text-emerald-800 border-emerald-200'),
            'verification_required' => static::badge('🟠', 'Verification Required', 'bg-amber-100 text-amber-800 border-amber-200'),
            'inactive' => static::badge('⚪', 'Inactive', 'bg-slate-100 text-slate-600 border-slate-200'),
            'missing' => static::badge('🔴', 'Missing Mapping', 'bg-rose-100 text-rose-800 border-rose-200'),
            'outdated' => static::badge('🟡', 'Outdated', 'bg-yellow-100 text-yellow-800 border-yellow-200'),
            default => static::badge('⚪', ucfirst(str_replace('_', ' ', $status)), 'bg-slate-100 text-slate-600 border-slate-200'),
        };
    }

    public static function destinationTypeLabel(string $type): string
    {
        return match ($type) {
            'bank' => 'Bank',
            'mobile_money' => 'Mobile Money',
            default => ucfirst(str_replace('_', ' ', $type)),
        };
    }

    public static function environmentLabel(?string $environment): string
    {
        if ($environment === null || $environment === '') {
            return 'Global';
        }

        return strtoupper($environment);
    }

    public static function gatewayFieldLabel(PaymentGateway $gateway, string $gatewayKey): string
    {
        if ($gateway->code === 'cgrate' && $gatewayKey === 'issuerName') {
            return 'cGrate issuerName';
        }

        return $gatewayKey;
    }

    public static function gatewayFieldHelp(PaymentGateway $gateway, string $gatewayKey): ?string
    {
        if ($gateway->code === 'cgrate' && $gatewayKey === 'issuerName') {
            return 'This is the issuerName value that will be sent to processCashDeposit().';
        }

        return null;
    }

    public static function gatewayValueHelp(PaymentGateway $gateway): ?string
    {
        if ($gateway->code === 'cgrate') {
            return 'In UAT this may be 543. In production this should match the value supplied by cGrate.';
        }

        return null;
    }

    public static function defaultGatewayKey(PaymentGateway $gateway): string
    {
        if ($gateway->code === 'cgrate') {
            return 'issuerName';
        }

        return 'identifier';
    }

    public static function fineEdgeDestinationLabel(PaymentGatewayDestinationMapping $mapping): string
    {
        if ($mapping->destination_type === 'bank') {
            return $mapping->financialInstitution?->name
                ?? ($mapping->financialInstitution?->code ?? 'Bank #'.$mapping->financial_institution_id);
        }

        return $mapping->channel?->name
            ?? ($mapping->channel?->code ?? 'Channel #'.$mapping->channel_id);
    }

    public static function issuerValueType(string $value): string
    {
        return ctype_digit($value) ? 'Numeric' : 'String';
    }

    public static function lastVerifiedLabel(?CarbonInterface $verifiedAt): string
    {
        if (! $verifiedAt) {
            return '—';
        }

        if ($verifiedAt->isToday()) {
            return 'Today';
        }

        if ($verifiedAt->isYesterday()) {
            return 'Yesterday';
        }

        return $verifiedAt->diffForHumans();
    }

    /**
     * @return array{emoji: string, label: string, class: string}
     */
    private static function badge(string $emoji, string $label, string $class): array
    {
        return [
            'emoji' => $emoji,
            'label' => $label,
            'class' => $class,
        ];
    }
}
