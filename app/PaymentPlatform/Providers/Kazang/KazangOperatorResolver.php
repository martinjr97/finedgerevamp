<?php

namespace App\PaymentPlatform\Providers\Kazang;

final class KazangOperatorResolver
{
    public function resolveFromPhone(?string $phone): ?string
    {
        $normalized = preg_replace('/\D+/', '', (string) $phone) ?? '';

        if ($normalized === '') {
            return null;
        }

        if (! str_starts_with($normalized, '260')) {
            if (str_starts_with($normalized, '0')) {
                $normalized = '260'.substr($normalized, 1);
            } elseif (strlen($normalized) === 9) {
                $normalized = '260'.$normalized;
            }
        }

        $prefix = substr($normalized, 0, 5);

        return match (true) {
            in_array($prefix, ['26097', '26077'], true) => 'airtel',
            in_array($prefix, ['26096', '26076'], true) => 'mtn',
            in_array($prefix, ['26095', '26075'], true) => 'zamtel',
            default => null,
        };
    }

    public function queueForOperator(string $operator): string
    {
        $queues = (array) config('kazang.amqp.queues', []);
        $queue = $queues[$operator] ?? null;

        if (! is_string($queue) || $queue === '') {
            throw new KazangException("No Kazang AMQP queue configured for operator [{$operator}].");
        }

        return $queue;
    }

    public function productIdForOperator(string $operator): string
    {
        $productIds = (array) config('kazang.product_ids', []);
        $productId = $productIds[$operator] ?? null;

        if (! is_string($productId) || $productId === '') {
            throw new KazangException("No Kazang product ID configured for operator [{$operator}].");
        }

        return $productId;
    }
}
