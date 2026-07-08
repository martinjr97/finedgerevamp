<?php

namespace App\Console\Commands;

use App\Sms\DTOs\SmsMessage;
use App\Sms\Enums\SmsCategory;
use App\Sms\Services\SmsService;
use App\Sms\Support\SmsPhoneNormalizer;
use Illuminate\Console\Command;

class SmsTestCommand extends Command
{
    protected $signature = 'sms:test
                            {--to= : Recipient MSISDN (e.g. 26097xxxxxxx)}
                            {--message= : Message body}
                            {--provider= : Override configured SMS provider}
                            {--force : Send even when SMS_ENABLED=false}';

    protected $description = 'Send a test SMS through the configured provider (safe defaults apply)';

    public function handle(SmsService $smsService, SmsPhoneNormalizer $phoneNormalizer): int
    {
        $to = trim((string) $this->option('to'));
        $message = trim((string) $this->option('message'));

        if ($to === '' || $message === '') {
            $this->error('Both --to and --message are required.');

            return self::FAILURE;
        }

        if (! $phoneNormalizer->isValid($to)) {
            $this->error('Invalid phone number format.');

            return self::FAILURE;
        }

        $provider = $this->option('provider') ?: (string) config('sms.provider', 'log');
        $force = (bool) $this->option('force');

        if (! config('sms.enabled', false) && ! $force) {
            $this->warn('SMS_ENABLED=false — use --force to send anyway.');
            $this->line('Provider: '.$provider);
            $this->line('To: '.$phoneNormalizer->mask($to));
            $this->line('Status: SKIPPED (disabled)');

            return self::SUCCESS;
        }

        $dto = new SmsMessage(
            phone: $to,
            body: $message,
            category: SmsCategory::General,
            messageType: 'test_send',
            forceSend: $force,
        );

        $result = $smsService->sendNow($dto, $provider);

        $this->info('SMS Test Result');
        $this->table(
            ['Field', 'Value'],
            [
                ['Provider', $result->provider],
                ['Successful', $result->successful ? 'yes' : 'no'],
                ['Accepted', $result->accepted ? 'yes' : 'no'],
                ['Skipped', $result->skipped() ? 'yes' : 'no'],
                ['Retryable', $result->retryable ? 'yes' : 'no'],
                ['HTTP Status', (string) ($result->httpStatus ?? '—')],
                ['Reference', $result->providerReference ?? '—'],
                ['Message', $result->responseMessage ?? '—'],
                ['Error', $result->error ?? '—'],
            ],
        );

        return $result->success() ? self::SUCCESS : self::FAILURE;
    }
}
