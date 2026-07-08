<?php

namespace Tests\Feature\PaymentGateway;

use App\PaymentPlatform\Providers\CGrate\CGrateClient;
use App\PaymentPlatform\Support\CGrateUatLogger;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class CGrateUatToolsTest extends TestCase
{
    use RefreshDatabase;

    public function test_get_available_cash_deposit_issuers_builds_soap_request_without_body_fields(): void
    {
        config([
            'cgrate.enabled' => true,
            'cgrate.username' => 'uat-user',
            'cgrate.password' => 'secret-password',
            'cgrate.base_url' => 'https://test.543.cgrate.co.zm',
        ]);

        Http::fake(function ($request) {
            $body = $request->body();
            $this->assertStringContainsString('getAvailableCashDepositIssuers', $body);
            $this->assertStringContainsString('<wsse:Username>uat-user</wsse:Username>', $body);

            return Http::response($this->issuersSoapResponse(['MTN', 'Airtel', 'Zanaco']), 200);
        });

        $result = app(CGrateClient::class)->getAvailableCashDepositIssuers();

        $this->assertSame(['MTN', 'Airtel', 'Zanaco'], $result['issuers']);
        $this->assertSame('getAvailableCashDepositIssuers', $result['raw']['operation'] ?? null);
    }

    public function test_issuer_command_fails_when_cgrate_disabled(): void
    {
        config(['cgrate.enabled' => false]);

        $exitCode = Artisan::call('cgrate:cash-deposit-issuers');

        $this->assertSame(1, $exitCode);
        $this->assertStringContainsString('disabled', Artisan::output());
    }

    public function test_issuer_command_does_not_print_credentials(): void
    {
        config([
            'cgrate.enabled' => true,
            'cgrate.username' => 'uat-user',
            'cgrate.password' => 'super-secret-pass',
            'cgrate.base_url' => 'https://test.543.cgrate.co.zm',
        ]);

        Http::fake([
            '*' => Http::response($this->issuersSoapResponse(['MTN', 'Airtel']), 200),
        ]);

        Log::shouldReceive('channel')->andReturnSelf();
        Log::shouldReceive('info')->andReturnNull();

        Artisan::call('cgrate:cash-deposit-issuers');
        $output = Artisan::output();

        $this->assertStringNotContainsString('super-secret-pass', $output);
        $this->assertStringNotContainsString('uat-user', $output);
        $this->assertStringContainsString('MTN', $output);
    }

    public function test_test_cash_deposit_dry_run_does_not_call_cgrate(): void
    {
        config([
            'cgrate.enabled' => true,
            'cgrate.username' => 'uat-user',
            'cgrate.password' => 'secret',
            'cgrate.base_url' => 'https://test.543.cgrate.co.zm',
        ]);

        Http::fake();

        $exitCode = Artisan::call('cgrate:test-cash-deposit', [
            '--amount' => 5,
            '--account' => '0978967132',
            '--issuer' => 'Airtel',
            '--dry-run' => true,
        ]);

        $this->assertSame(0, $exitCode);
        Http::assertNothingSent();
        $output = Artisan::output();
        $this->assertStringContainsString('Dry run complete', $output);
        $this->assertStringContainsString('FINEDGE-UAT-', $output);
    }

    public function test_test_cash_deposit_requires_account_and_issuer(): void
    {
        config([
            'cgrate.enabled' => true,
            'cgrate.username' => 'uat-user',
            'cgrate.password' => 'secret',
            'cgrate.base_url' => 'https://test.543.cgrate.co.zm',
        ]);

        $exitCode = Artisan::call('cgrate:test-cash-deposit', [
            '--dry-run' => true,
        ]);

        $this->assertSame(1, $exitCode);
        $this->assertStringContainsString('--account and --issuer', Artisan::output());
    }

    public function test_test_cash_deposit_live_call_requires_force_flag_in_non_interactive_mode(): void
    {
        config([
            'cgrate.enabled' => true,
            'cgrate.username' => 'uat-user',
            'cgrate.password' => 'secret',
            'cgrate.base_url' => 'https://test.543.cgrate.co.zm',
        ]);

        Http::fake();

        $exitCode = Artisan::call('cgrate:test-cash-deposit', [
            '--amount' => 5,
            '--account' => '0978967132',
            '--issuer' => 'Airtel',
            '--no-interaction' => true,
        ]);

        $this->assertSame(0, $exitCode);
        Http::assertNothingSent();
        $this->assertStringContainsString('Aborted', Artisan::output());
    }

    public function test_test_cash_deposit_force_sends_soap_without_password_in_logs(): void
    {
        config([
            'cgrate.enabled' => true,
            'cgrate.username' => 'uat-user',
            'cgrate.password' => 'secret-password',
            'cgrate.base_url' => 'https://test.543.cgrate.co.zm',
            'cgrate.uat.log_enabled' => true,
        ]);

        Http::fake(function ($request) {
            $body = $request->body();
            $this->assertStringContainsString('processCashDeposit', $body);
            $this->assertStringContainsString('<issuerName>543</issuerName>', $body);
            $this->assertStringContainsString('FINEDGE-UAT-REF-001', $body);

            return Http::response($this->paymentSoapResponse(0, 'Successful', 'PAY-001'), 200);
        });

        $logged = [];
        Log::shouldReceive('channel')->andReturnSelf();
        Log::shouldReceive('info')->andReturnUsing(function ($message, $context) use (&$logged) {
            $logged[] = ['message' => $message, 'context' => $context];
        });

        $exitCode = Artisan::call('cgrate:test-cash-deposit', [
            '--amount' => 5,
            '--account' => '0978967132',
            '--issuer' => 'Airtel',
            '--reference' => 'FINEDGE-UAT-REF-001',
            '--force' => true,
        ]);

        $this->assertSame(0, $exitCode);
        Http::assertSentCount(1);

        $encoded = json_encode($logged);
        $this->assertIsString($encoded);
        $this->assertStringNotContainsString('secret-password', $encoded);
    }

    public function test_uat_logger_redacts_password_in_xml_snippets(): void
    {
        $logger = app(CGrateUatLogger::class);
        $redacted = $logger->redact([
            'xml' => '<wsse:Password Type="text">abc123</wsse:Password>',
            'password' => 'abc123',
        ]);

        $this->assertSame('[REDACTED]', $redacted['password']);
        $this->assertStringContainsString('[REDACTED]', $redacted['xml']);
        $this->assertStringNotContainsString('abc123', $redacted['xml']);
    }

    /**
     * @param  list<string>  $issuers
     */
    private function issuersSoapResponse(array $issuers): string
    {
        $items = '';
        foreach ($issuers as $issuer) {
            $items .= '<issuer>'.htmlspecialchars($issuer, ENT_XML1).'</issuer>';
        }

        return '<?xml version="1.0" encoding="UTF-8"?>'
            .'<soapenv:Envelope xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/">'
            .'<soapenv:Body>'
            .'<getAvailableCashDepositIssuersResponse>'
            .'<return>'.$items.'</return>'
            .'</getAvailableCashDepositIssuersResponse>'
            .'</soapenv:Body>'
            .'</soapenv:Envelope>';
    }

    private function paymentSoapResponse(int $code, string $message, string $paymentId): string
    {
        return '<?xml version="1.0" encoding="UTF-8"?>'
            .'<soapenv:Envelope xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/">'
            .'<soapenv:Body>'
            .'<processCashDepositResponse>'
            .'<return>'
            .'<responseCode>'.$code.'</responseCode>'
            .'<responseMessage>'.$message.'</responseMessage>'
            .'<paymentID>'.$paymentId.'</paymentID>'
            .'</return>'
            .'</processCashDepositResponse>'
            .'</soapenv:Body>'
            .'</soapenv:Envelope>';
    }
}
