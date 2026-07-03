<?php

namespace App\PaymentPlatform\Services;

use App\Models\Loan;
use App\Models\PaymentGatewayAttempt;
use App\Models\Repayment;
use App\PaymentPlatform\Enums\GatewayDirection;
use App\PaymentPlatform\Jobs\DispatchGatewayCollectionJob;
use App\PaymentPlatform\Jobs\DispatchGatewayDisbursementJob;
use App\PaymentPlatform\Jobs\QueryGatewayAttemptStatusJob;
use App\Support\Queue\FinancialQueue;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class FailedFinancialJobService
{
    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function list(int $limit = 50): Collection
    {
        $queues = FinancialQueue::allFinancialQueueNames();
        $connection = FinancialQueue::connection();

        return DB::table('failed_jobs')
            ->where('connection', $connection)
            ->whereIn('queue', $queues)
            ->orderByDesc('failed_at')
            ->limit($limit)
            ->get()
            ->map(fn (object $row) => $this->present($row));
    }

    public function count(): int
    {
        return DB::table('failed_jobs')
            ->where('connection', FinancialQueue::connection())
            ->whereIn('queue', FinancialQueue::allFinancialQueueNames())
            ->count();
    }

    public function find(string $uuid): ?array
    {
        $row = DB::table('failed_jobs')
            ->where('uuid', $uuid)
            ->where('connection', FinancialQueue::connection())
            ->whereIn('queue', FinancialQueue::allFinancialQueueNames())
            ->first();

        return $row ? $this->present($row, includeException: true) : null;
    }

    public function retry(string $uuid): bool
    {
        $row = DB::table('failed_jobs')->where('uuid', $uuid)->first();
        if (! $row) {
            return false;
        }

        Artisan::call('queue:retry', ['id' => $uuid]);

        return true;
    }

    public function discard(string $uuid): bool
    {
        $row = DB::table('failed_jobs')->where('uuid', $uuid)->first();
        if (! $row) {
            return false;
        }

        Artisan::call('queue:forget', ['id' => $uuid]);

        return true;
    }

    /**
     * @return array<string, mixed>
     */
    private function present(object $row, bool $includeException = false): array
    {
        $attemptId = $this->extractAttemptId((string) $row->payload);
        $attempt = $attemptId ? $this->loadAttempt($attemptId) : null;

        $direction = $attempt?->direction?->value;
        $loanId = null;
        $customerId = null;
        $customerName = null;
        $loanNumber = null;

        if ($attempt) {
            if ($attempt->direction === GatewayDirection::Disbursement && $attempt->attemptable instanceof Loan) {
                $loan = $attempt->attemptable;
                $loanId = $loan->id;
                $loanNumber = $loan->loan_number;
                $customerId = $loan->customer_id;
                $customerName = $loan->customer?->full_name;
            } elseif ($attempt->attemptable instanceof Repayment) {
                $repayment = $attempt->attemptable;
                $customerId = $repayment->customer_id;
                $customerName = $repayment->customer?->full_name;
                $loanId = $repayment->loanRepayments()->value('loan_id');
                if ($loanId) {
                    $loanNumber = Loan::query()->whereKey($loanId)->value('loan_number');
                }
            }
        }

        $data = [
            'uuid' => $row->uuid,
            'queue' => $row->queue,
            'connection' => $row->connection,
            'failed_at' => Carbon::parse($row->failed_at),
            'job_class' => $this->extractJobClass((string) $row->payload),
            'correlation_id' => $attempt?->correlationId(),
            'gateway_code' => $attempt?->paymentGateway?->code,
            'direction' => $direction,
            'loan_id' => $loanId,
            'loan_number' => $loanNumber,
            'customer_id' => $customerId,
            'customer_name' => $customerName,
            'attempt_id' => $attemptId,
            'exception_summary' => $this->summarizeException((string) $row->exception),
        ];

        if ($includeException) {
            $data['exception_detail'] = Str::limit((string) $row->exception, 2000);
        }

        return $data;
    }

    private function loadAttempt(int $attemptId): ?PaymentGatewayAttempt
    {
        $attempt = PaymentGatewayAttempt::query()
            ->with('paymentGateway')
            ->find($attemptId);

        if (! $attempt) {
            return null;
        }

        if ($attempt->attemptable_type === Loan::class || $attempt->attemptable_type === Repayment::class) {
            $attempt->load('attemptable.customer');
        } else {
            $attempt->load('attemptable');
        }

        return $attempt;
    }

    private function extractAttemptId(string $payload): ?int
    {
        $decoded = json_decode($payload, true);
        if (is_array($decoded)) {
            $command = $decoded['data']['command'] ?? null;
            if (is_string($command)) {
                try {
                    $job = unserialize($command, ['allowed_classes' => true]);
                    if (is_object($job) && property_exists($job, 'paymentGatewayAttemptId')) {
                        return (int) $job->paymentGatewayAttemptId;
                    }
                } catch (\Throwable) {
                    // Fall through to pattern matching.
                }
            }
        }

        if (preg_match('/paymentGatewayAttemptId";i:(\d+)/', $payload, $matches)) {
            return (int) $matches[1];
        }

        if (preg_match('/s:24:"paymentGatewayAttemptId";i:(\d+)/', $payload, $matches)) {
            return (int) $matches[1];
        }

        return null;
    }

    private function extractJobClass(string $payload): ?string
    {
        $decoded = json_decode($payload, true);
        $displayName = $decoded['displayName'] ?? null;

        if (is_string($displayName)) {
            return class_basename($displayName);
        }

        foreach ([
            DispatchGatewayCollectionJob::class,
            DispatchGatewayDisbursementJob::class,
            QueryGatewayAttemptStatusJob::class,
        ] as $class) {
            if (str_contains($payload, class_basename($class))) {
                return class_basename($class);
            }
        }

        return null;
    }

    private function summarizeException(string $exception): string
    {
        $lines = preg_split('/\R/', trim($exception)) ?: [];

        return Str::limit($lines[0] ?? 'Unknown error', 180);
    }
}
