<?php

namespace Tests\Feature\Queue;

use App\Models\PaymentGateway;
use App\Models\PaymentGatewayAttempt;
use App\Notifications\AdminPasswordResetLink;
use App\Notifications\AdminPasswordResetOtp;
use App\PaymentPlatform\Enums\FinancialJobPriority;
use App\PaymentPlatform\Enums\GatewayAttemptPurpose;
use App\PaymentPlatform\Enums\GatewayAttemptStatus;
use App\PaymentPlatform\Enums\GatewayDirection;
use App\PaymentPlatform\Enums\GatewayPaymentMethod;
use App\PaymentPlatform\Enums\PaymentGatewayStatus;
use App\PaymentPlatform\Enums\PaymentGatewayType;
use App\PaymentPlatform\Jobs\DispatchGatewayCollectionJob;
use App\PaymentPlatform\Jobs\DispatchGatewayDisbursementJob;
use App\PaymentPlatform\Jobs\QueryGatewayAttemptStatusJob;
use App\PaymentPlatform\Providers\CGrate\CGratePaymentGateway;
use App\Support\Queue\ApplicationQueue;
use App\Support\Queue\FinancialQueue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class GatewayQueueAssignmentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'queue.default' => 'redis',
            'queues.connections.financial' => 'redis-financial',
            'queues.connections.default' => 'redis',
            'queues.names.payments_high' => 'payments-high',
            'queues.names.payments' => 'payments',
            'queues.names.disbursements_high' => 'disbursements-high',
            'queues.names.disbursements' => 'disbursements',
            'queues.names.notifications' => 'notifications',
            'queues.names.reports' => 'reports',
            'queues.names.maintenance' => 'maintenance',
            'queues.retries.financial_initiation' => 1,
            'queues.retries.financial_status' => 5,
            'queues.retries.notifications' => 3,
        ]);
    }

    public function test_collection_initiation_job_uses_payments_high_on_financial_connection(): void
    {
        Queue::fake();

        DispatchGatewayCollectionJob::dispatch(1);

        Queue::assertPushed(DispatchGatewayCollectionJob::class, function (DispatchGatewayCollectionJob $job) {
            return $job->queue === FinancialQueue::paymentsHigh()
                && $job->connection === FinancialQueue::connection()
                && $job->tries === 1;
        });
    }

    public function test_disbursement_initiation_job_uses_disbursements_high_on_financial_connection(): void
    {
        Queue::fake();

        DispatchGatewayDisbursementJob::dispatch(1);

        Queue::assertPushed(DispatchGatewayDisbursementJob::class, function (DispatchGatewayDisbursementJob $job) {
            return $job->queue === FinancialQueue::disbursementsHigh()
                && $job->connection === FinancialQueue::connection()
                && $job->tries === 1
                && $job->timeout === (int) config('cgrate.disbursement_timeout', 120);
        });
    }

    public function test_collection_polling_job_uses_payments_queue(): void
    {
        Queue::fake();

        $attempt = $this->createAttempt(GatewayDirection::Collection);

        QueryGatewayAttemptStatusJob::dispatchForAttempt($attempt->id);

        Queue::assertPushed(QueryGatewayAttemptStatusJob::class, function (QueryGatewayAttemptStatusJob $job) {
            return $job->queue === FinancialQueue::payments()
                && $job->connection === FinancialQueue::connection()
                && $job->tries === 5;
        });
    }

    public function test_collection_callback_job_uses_payments_high_queue(): void
    {
        Queue::fake();

        $attempt = $this->createAttempt(GatewayDirection::Collection);

        QueryGatewayAttemptStatusJob::dispatchForAttempt($attempt->id, null, FinancialJobPriority::High);

        Queue::assertPushed(QueryGatewayAttemptStatusJob::class, function (QueryGatewayAttemptStatusJob $job) {
            return $job->queue === FinancialQueue::paymentsHigh();
        });
    }

    public function test_disbursement_attempts_are_not_polled(): void
    {
        $attempt = $this->createAttempt(GatewayDirection::Disbursement);

        $this->expectException(\InvalidArgumentException::class);

        QueryGatewayAttemptStatusJob::dispatchForAttempt($attempt->id);
    }

    public function test_disbursement_status_job_handle_is_no_op(): void
    {
        $attempt = $this->createAttempt(GatewayDirection::Disbursement);

        $job = new QueryGatewayAttemptStatusJob($attempt->id);
        $job->handle(app(\App\PaymentPlatform\Services\GatewayIntegrationService::class));

        $this->assertSame(0, $attempt->fresh()->query_attempts);
    }

    public function test_collection_job_horizon_tags_include_payment_and_correlation(): void
    {
        $attempt = $this->createAttempt(GatewayDirection::Collection);
        $job = new DispatchGatewayCollectionJob($attempt->id);
        $tags = $job->tags();

        $this->assertContains('payment', $tags);
        $this->assertContains('direction:collection', $tags);
        $this->assertContains('correlation:'.$attempt->correlationId(), $tags);
        $this->assertContains('gateway:'.$attempt->paymentGateway->code, $tags);
    }

    public function test_disbursement_job_horizon_tags_include_disbursement_and_correlation(): void
    {
        $attempt = $this->createAttempt(GatewayDirection::Disbursement);
        $job = new DispatchGatewayDisbursementJob($attempt->id);
        $tags = $job->tags();

        $this->assertContains('disbursement', $tags);
        $this->assertContains('direction:disbursement', $tags);
        $this->assertContains('correlation:'.$attempt->correlationId(), $tags);
    }

    public function test_admin_password_reset_otp_uses_notifications_queue(): void
    {
        $notification = new AdminPasswordResetOtp('123456');

        $this->assertSame(ApplicationQueue::notifications(), $notification->queue);
        $this->assertSame(ApplicationQueue::connection(), $notification->connection);
        $this->assertSame(3, $notification->tries);
    }

    public function test_admin_password_reset_link_uses_notifications_queue(): void
    {
        $notification = new AdminPasswordResetLink('https://example.com/reset');

        $this->assertSame(ApplicationQueue::notifications(), $notification->queue);
        $this->assertSame(ApplicationQueue::connection(), $notification->connection);
        $this->assertSame(3, $notification->tries);
    }

    public function test_application_queue_helpers_return_expected_names(): void
    {
        $this->assertSame('reports', ApplicationQueue::reports());
        $this->assertSame('maintenance', ApplicationQueue::maintenance());
        $this->assertSame('payments', FinancialQueue::payments());
        $this->assertSame('disbursements', FinancialQueue::disbursements());
    }

    public function test_payment_gateway_attempt_correlation_id_matches_internal_reference(): void
    {
        $attempt = $this->createAttempt(GatewayDirection::Collection);

        $this->assertSame($attempt->internal_reference, $attempt->correlationId());
    }

    public function test_failed_financial_job_present_includes_correlation_and_discard_removes_record(): void
    {
        $attempt = $this->createAttempt(GatewayDirection::Collection);
        $uuid = (string) \Illuminate\Support\Str::uuid();

        DB::table('failed_jobs')->insert([
            'uuid' => $uuid,
            'connection' => FinancialQueue::connection(),
            'queue' => FinancialQueue::paymentsHigh(),
            'payload' => json_encode([
                'displayName' => DispatchGatewayCollectionJob::class,
                'job' => 'Illuminate\\Queue\\CallQueuedHandler@call',
                'data' => [
                    'commandName' => DispatchGatewayCollectionJob::class,
                    'command' => serialize(new DispatchGatewayCollectionJob($attempt->id)),
                ],
            ]),
            'exception' => "RuntimeException: Test failure\n#0 /tmp/test.php(1)",
            'failed_at' => now(),
        ]);

        $service = app(\App\PaymentPlatform\Services\FailedFinancialJobService::class);
        $this->assertSame(1, $service->count());

        $presented = $service->find($uuid);
        $this->assertNotNull($presented);
        $this->assertSame($attempt->correlationId(), $presented['correlation_id']);
        $this->assertStringContainsString('Test failure', $presented['exception_summary']);

        $this->assertTrue($service->discard($uuid));
        $this->assertDatabaseMissing('failed_jobs', ['uuid' => $uuid]);
    }

    private function createAttempt(GatewayDirection $direction): PaymentGatewayAttempt
    {
        $gateway = PaymentGateway::create([
            'name' => 'Test Gateway',
            'code' => 'test-'.strtolower($direction->value).'-'.uniqid(),
            'provider_class' => CGratePaymentGateway::class,
            'type' => PaymentGatewayType::Both,
            'status' => PaymentGatewayStatus::Active,
            'supports_collections' => true,
            'supports_disbursements' => true,
        ]);

        return PaymentGatewayAttempt::create([
            'payment_gateway_id' => $gateway->id,
            'direction' => $direction,
            'purpose' => $direction === GatewayDirection::Collection
                ? GatewayAttemptPurpose::LoanRepayment
                : GatewayAttemptPurpose::LoanDisbursement,
            'attemptable_type' => PaymentGateway::class,
            'attemptable_id' => $gateway->id,
            'internal_reference' => 'FINEDGE-TEST-'.strtoupper($direction->value),
            'provider_reference' => 'FINEDGE-TEST-'.strtoupper($direction->value),
            'payment_method' => GatewayPaymentMethod::MobileMoney,
            'amount' => 100,
            'currency' => 'ZMW',
            'status' => GatewayAttemptStatus::Pending,
        ]);
    }
}
