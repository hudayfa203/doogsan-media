<?php

namespace Tests\Unit;

use App\Services\TwoFactor;
use PHPUnit\Framework\TestCase;

class TwoFactorTest extends TestCase
{
    public function test_rfc6238_reference_vector(): void
    {
        $totp = new TwoFactor;
        // RFC 6238 SHA-1 secret "12345678901234567890", T=59 → 94287082 (last 6 digits: 287082)
        $secret = $totp->base32Encode('12345678901234567890');
        $this->assertSame('287082', $totp->code($secret, intdiv(59, 30)));
        $this->assertTrue($totp->verify($secret, '287082', 0, 59));
        $this->assertFalse($totp->verify($secret, '000000', 0, 59));
    }
}
