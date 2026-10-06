<?php

namespace Tests\Unit\PaymentPlatform;

use App\PaymentPlatform\Providers\Kazang\KazangOperatorResolver;
use Tests\TestCase;

class KazangOperatorResolverTest extends TestCase
{
    public function test_resolves_operators_from_zambian_msisdn_prefixes(): void
    {
        $resolver = new KazangOperatorResolver;

        $this->assertSame('airtel', $resolver->resolveFromPhone('260971234567'));
        $this->assertSame('mtn', $resolver->resolveFromPhone('260961234567'));
        $this->assertSame('zamtel', $resolver->resolveFromPhone('260951234567'));
        $this->assertNull($resolver->resolveFromPhone('260991234567'));
    }

    public function test_normalizes_local_numbers_before_resolving_operator(): void
    {
        $resolver = new KazangOperatorResolver;

        $this->assertSame('airtel', $resolver->resolveFromPhone('0971234567'));
        $this->assertSame('mtn', $resolver->resolveFromPhone('961234567'));
    }
}
