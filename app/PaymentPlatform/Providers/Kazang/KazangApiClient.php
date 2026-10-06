<?php

namespace App\PaymentPlatform\Providers\Kazang;

use Illuminate\Support\Facades\Log;
use IXR\Client\ClientSSL;

class KazangApiClient
{
    /**
     * @return array<string, mixed>
     */
    public function authenticate(): array
    {
        return $this->call('authClient', [
            'username' => (string) config('kazang.api.username'),
            'password' => (string) config('kazang.api.password'),
            'channel' => (string) config('kazang.api.channel'),
        ]);
    }

    /**
     * Live merchant float from Kazang authClient (legacy pattern).
     *
     * @return array{
     *     balance: float,
     *     currency: string,
     *     response_code: int|string|null,
     *     response_message: string|null,
     *     checked_at: string,
     *     raw: array<string, mixed>
     * }
     */
    public function getMerchantBalance(): array
    {
        $response = $this->authenticate();

        if ((string) ($response['response_code'] ?? '') !== '0') {
            throw new KazangException(
                (string) ($response['response_message'] ?? 'Kazang authentication failed.'),
            );
        }

        if (! isset($response['balance']) || $response['balance'] === '') {
            throw new KazangException('Kazang did not return a merchant balance.');
        }

        return [
            'balance' => $this->normalizeBalance($response['balance']),
            'currency' => (string) config('kazang.default_currency', 'ZMW'),
            'response_code' => $response['response_code'],
            'response_message' => isset($response['response_message'])
                ? (string) $response['response_message']
                : null,
            'checked_at' => now()->toIso8601String(),
            'raw' => ['kazang' => $response],
        ];
    }

    private function normalizeBalance(mixed $balance): float
    {
        $amount = (float) $balance;
        $mode = (string) config('kazang.balance_mode', 'kwacha');

        if ($mode === 'minor_units') {
            return round($amount / 100, 2);
        }

        return $amount;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function nfsCashIn(array $payload): array
    {
        return $this->call('nfsCashIn', $payload);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function confirm(array $payload): array
    {
        return $this->call('confirm', $payload);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function call(string $method, array $payload): array
    {
        if (! (bool) config('kazang.enabled')) {
            throw new KazangException('Kazang payments are disabled.');
        }

        $host = (string) config('kazang.api.host');
        $path = (string) config('kazang.api.path');

        Log::channel('stack')->info('Kazang API request', [
            'method' => $method,
            'host' => $host,
            'path' => $path,
        ]);

        $client = new ClientSSL($host, $path);
        $client->query($method, $payload);
        $response = $client->getResponse();

        if (! is_array($response)) {
            throw new KazangException('Unexpected Kazang API response format.');
        }

        return $response;
    }
}
