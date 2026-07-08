<?php

namespace App\PaymentPlatform\Providers\CGrate;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

final class CGrateClient
{
    public function processCustomerPayment(string $transactionAmount, string $customerMobile, string $paymentReference): CGratePaymentResponse
    {
        $xml = $this->buildEnvelope('processCustomerPayment', [
            'transactionAmount' => $transactionAmount,
            'customerMobile' => $customerMobile,
            'paymentReference' => $paymentReference,
        ]);

        return $this->send('processCustomerPayment', $xml, [
            'transactionAmount' => $transactionAmount,
            'customerMobile' => $customerMobile,
            'paymentReference' => $paymentReference,
        ]);
    }

    public function queryCustomerPayment(string $paymentReference): CGratePaymentResponse
    {
        $xml = $this->buildEnvelope('queryCustomerPayment', [
            'paymentReference' => $paymentReference,
        ]);

        return $this->send('queryCustomerPayment', $xml, [
            'paymentReference' => $paymentReference,
        ]);
    }

    public function processCashDeposit(
        string $transactionAmount,
        string $customerAccount,
        string $issuerName,
        string $depositorReference
    ): CGratePaymentResponse {
        $xml = $this->buildEnvelope('processCashDeposit', [
            'transactionAmount' => $transactionAmount,
            'customerAccount' => $customerAccount,
            'issuerName' => $issuerName,
            'depositorReference' => $depositorReference,
        ]);

        return $this->send('processCashDeposit', $xml, [
            'transactionAmount' => $transactionAmount,
            'customerAccount' => $customerAccount,
            'issuerName' => $issuerName,
            'depositorReference' => $depositorReference,
        ], timeoutSeconds: (int) config('cgrate.disbursement_timeout', 120));
    }

    /**
     * Discover cash-deposit issuers supported by the merchant account.
     *
     * @return array{issuers: list<string>, raw: array<string, mixed>}
     */
    public function getAvailableCashDepositIssuers(): array
    {
        $operation = 'getAvailableCashDepositIssuers';
        $xml = $this->buildEnvelope($operation, []);
        $body = $this->postSoap($operation, $xml, []);

        return $this->parseIssuersResponse($operation, $body);
    }

    /**
     * @param  array<string, string>  $fields
     */
    private function buildEnvelope(string $operation, array $fields): string
    {
        $ns = (string) config('cgrate.soap.namespace', 'http://konik.cgrate.com');
        $username = (string) config('cgrate.username');
        $password = (string) config('cgrate.password');

        if (trim($username) === '' || trim($password) === '') {
            throw new CGrateException('cGrate credentials are not configured.');
        }

        $body = '';
        foreach ($fields as $key => $value) {
            $body .= '<'.$key.'>'.$this->escape($value).'</'.$key.'>';
        }

        return '<?xml version="1.0" encoding="UTF-8"?>'
            .'<soapenv:Envelope xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/" xmlns:kon="'.$this->escapeAttr($ns).'">'
            .'<soapenv:Header>'
            .'<wsse:Security xmlns:wsse="http://docs.oasis-open.org/wss/2004/01/oasis-200401-wss-wssecurity-secext-1.0.xsd" soapenv:mustUnderstand="1">'
            .'<wsse:UsernameToken xmlns:wsu="http://docs.oasis-open.org/wss/2004/01/oasis-200401-wss-wssecurity-utility-1.0.xsd" wsu:Id="UsernameToken-1">'
            .'<wsse:Username>'.$this->escape($username).'</wsse:Username>'
            .'<wsse:Password Type="http://docs.oasis-open.org/wss/2004/01/oasis-200401-wss-username-token-profile-1.0#PasswordText">'.$this->escape($password).'</wsse:Password>'
            .'</wsse:UsernameToken>'
            .'</wsse:Security>'
            .'</soapenv:Header>'
            .'<soapenv:Body>'
            .'<kon:'.$operation.'>'.$body.'</kon:'.$operation.'>'
            .'</soapenv:Body>'
            .'</soapenv:Envelope>';
    }

    /**
     * @param  array<string, mixed>  $safeRequest
     */
    private function send(string $operation, string $requestXml, array $safeRequest, ?int $timeoutSeconds = null): CGratePaymentResponse
    {
        $body = $this->postSoap($operation, $requestXml, $safeRequest, $timeoutSeconds);
        $parsed = $this->parseXmlResponse($body);

        return new CGratePaymentResponse(
            responseCode: $parsed['response_code'],
            responseMessage: $parsed['response_message'],
            paymentId: $parsed['payment_id'],
            raw: [
                'operation' => $operation,
                'http_status' => $parsed['http_status'],
                'request' => $safeRequest,
                'response' => [
                    'responseCode' => $parsed['response_code'],
                    'responseMessage' => $parsed['response_message'],
                    'paymentID' => $parsed['payment_id'],
                ],
            ],
        );
    }

    /**
     * @param  array<string, mixed>  $safeRequest
     */
    private function postSoap(string $operation, string $requestXml, array $safeRequest, ?int $timeoutSeconds = null): string
    {
        $baseUrl = rtrim((string) config('cgrate.base_url'), '/');
        $path = (string) config('cgrate.soap.endpoint_path', '/Konik/KonikWs');
        $url = $baseUrl.$path;

        $timeout = $timeoutSeconds ?? (int) config('cgrate.timeout', 30);
        $connectTimeout = (int) config('cgrate.connect_timeout', 10);
        $verifySsl = (bool) config('cgrate.verify_ssl', true);
        $contentType = (string) config('cgrate.soap.content_type', 'application/soap+xml; charset=utf-8');

        try {
            $pending = Http::timeout($timeout)
                ->connectTimeout($connectTimeout)
                ->withHeaders([
                    'Content-Type' => $contentType,
                    'SOAPAction' => '',
                ])
                ->withBody($requestXml, $contentType);

            if (! $verifySsl) {
                $pending = $pending->withoutVerifying();
            }

            $response = $pending->post($url);
        } catch (ConnectionException $e) {
            throw new CGrateException('cGrate connection failed (timeout / network error).', [
                'operation' => $operation,
                'url' => $url,
                'request' => $safeRequest,
            ], previous: $e);
        }

        $body = (string) $response->body();
        if (trim($body) === '') {
            throw new CGrateException('cGrate returned an empty response body.', [
                'operation' => $operation,
                'url' => $url,
                'http_status' => $response->status(),
                'request' => $safeRequest,
            ]);
        }

        return $body;
    }

    /**
     * @return array{response_code: int|null, response_message: string, payment_id: string|null, http_status: int|null}
     */
    private function parseXmlResponse(string $xml, ?int $httpStatus = null): array
    {
        $prev = libxml_use_internal_errors(true);
        try {
            $doc = new \DOMDocument;
            $doc->resolveExternals = false;
            $doc->substituteEntities = false;
            $doc->validateOnParse = false;

            if (! $doc->loadXML($xml, LIBXML_NONET)) {
                $errors = array_map(
                    fn ($e) => trim($e->message),
                    libxml_get_errors() ?: [],
                );
                libxml_clear_errors();

                throw new CGrateException('Invalid XML returned by cGrate.', [
                    'errors' => array_values(array_filter($errors)),
                ]);
            }

            $xpath = new \DOMXPath($doc);

            $fault = $xpath->query('//*[local-name()="Fault"]')->item(0);
            if ($fault) {
                $faultString = $xpath->query('.//*[local-name()="faultstring" or local-name()="Reason"]', $fault)->item(0);
                $faultText = $faultString?->textContent ? trim((string) $faultString->textContent) : 'SOAP Fault';

                throw new CGrateException('cGrate SOAP fault: '.$faultText);
            }

            $return = $xpath->query('//*[local-name()="return"]')->item(0);
            if (! $return) {
                throw new CGrateException('cGrate response missing <return> node.');
            }

            $codeNode = $xpath->query('.//*[local-name()="responseCode"]', $return)->item(0);
            $msgNode = $xpath->query('.//*[local-name()="responseMessage"]', $return)->item(0);
            $pidNode = $xpath->query('.//*[local-name()="paymentID"]', $return)->item(0);

            $code = null;
            if ($codeNode && trim((string) $codeNode->textContent) !== '') {
                $code = (int) trim((string) $codeNode->textContent);
            }

            $message = $msgNode?->textContent ? trim((string) $msgNode->textContent) : '';
            $paymentId = $pidNode?->textContent ? trim((string) $pidNode->textContent) : null;

            return [
                'response_code' => $code,
                'response_message' => $message,
                'payment_id' => $paymentId !== '' ? $paymentId : null,
                'http_status' => $httpStatus,
            ];
        } finally {
            libxml_use_internal_errors($prev);
        }
    }

    /**
     * @return array{issuers: list<string>, raw: array<string, mixed>}
     */
    private function parseIssuersResponse(string $operation, string $xml): array
    {
        $prev = libxml_use_internal_errors(true);

        try {
            $doc = new \DOMDocument;
            $doc->resolveExternals = false;
            $doc->substituteEntities = false;
            $doc->validateOnParse = false;

            if (! $doc->loadXML($xml, LIBXML_NONET)) {
                throw new CGrateException('Invalid XML returned by cGrate issuer discovery.', [
                    'operation' => $operation,
                ]);
            }

            $xpath = new \DOMXPath($doc);

            $fault = $xpath->query('//*[local-name()="Fault"]')->item(0);
            if ($fault) {
                $faultString = $xpath->query('.//*[local-name()="faultstring" or local-name()="Reason"]', $fault)->item(0);
                $faultText = $faultString?->textContent ? trim((string) $faultString->textContent) : 'SOAP Fault';

                throw new CGrateException('cGrate SOAP fault during issuer discovery: '.$faultText);
            }

            $returnNodes = $xpath->query('//*[local-name()="return"]');
            $issuers = [];
            $responseCode = null;
            $responseMessage = '';

            if ($returnNodes !== false) {
                foreach ($returnNodes as $return) {
                    $codeNode = $xpath->query('.//*[local-name()="responseCode"]', $return)->item(0);
                    if ($codeNode && trim((string) $codeNode->textContent) !== '') {
                        $responseCode = (int) trim((string) $codeNode->textContent);
                    }

                    $msgNode = $xpath->query('.//*[local-name()="responseMessage"]', $return)->item(0);
                    if ($msgNode && trim((string) $msgNode->textContent) !== '') {
                        $responseMessage = trim((string) $msgNode->textContent);
                    }

                    $issuerNodes = $xpath->query(
                        './/*[local-name()="issuer" or local-name()="issuers" or local-name()="issuerName" or local-name()="name"]',
                        $return
                    );

                    if ($issuerNodes !== false) {
                        foreach ($issuerNodes as $issuerNode) {
                            $name = trim((string) $issuerNode->textContent);
                            // cGrate issuer identifiers can be numeric (e.g. "543").
                            if ($name !== '') {
                                $issuers[] = $name;
                            }
                        }
                    }

                    $directText = trim(preg_replace('/\s+/', ' ', (string) $return->textContent) ?? '');
                    if ($directText !== '' && $issuers === [] && ! str_contains(strtolower($directText), 'responsecode')) {
                        $issuers[] = $directText;
                    }
                }
            }

            if ($issuers === []) {
                $stringNodes = $xpath->query('//*[local-name()="string"]');
                if ($stringNodes !== false) {
                    foreach ($stringNodes as $stringNode) {
                        $name = trim((string) $stringNode->textContent);
                        if ($name !== '') {
                            $issuers[] = $name;
                        }
                    }
                }
            }

            $issuers = array_values(array_unique(array_filter(array_map('trim', $issuers))));

            if ($responseCode !== null && $responseCode !== 0 && $issuers === []) {
                throw new CGrateException('cGrate issuer discovery failed.', [
                    'operation' => $operation,
                    'response_code' => $responseCode,
                    'response_message' => $responseMessage,
                ]);
            }

            return [
                'issuers' => $issuers,
                'raw' => [
                    'operation' => $operation,
                    'response_code' => $responseCode,
                    'response_message' => $responseMessage,
                    'issuer_count' => count($issuers),
                    'xml_excerpt' => mb_substr($xml, 0, 4000),
                ],
            ];
        } finally {
            libxml_use_internal_errors($prev);
        }
    }

    private function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_XML1 | ENT_COMPAT, 'UTF-8');
    }

    private function escapeAttr(string $value): string
    {
        return htmlspecialchars($value, ENT_XML1 | ENT_COMPAT, 'UTF-8');
    }
}
