<?php

namespace App\Tests\Entity;

use App\Entity\UnguessableCode;
use PHPUnit\Framework\TestCase;

class UnguessableCodeTest extends TestCase
{
    public function testHasExpiredWhenPast(): void
    {
        $code = new UnguessableCode();
        $code->setExpiresAt(new \DateTimeImmutable('-1 hour'));

        $this->assertTrue($code->hasExpired());
    }

    public function testHasNotExpiredWhenFuture(): void
    {
        $code = new UnguessableCode();
        $code->setExpiresAt(new \DateTimeImmutable('+1 hour'));

        $this->assertFalse($code->hasExpired());
    }

    public function testHasNotExpiredWhenNull(): void
    {
        $code = new UnguessableCode();

        $this->assertFalse($code->hasExpired());
    }

    public function testHasNoRemainingHitsWhenZero(): void
    {
        $code = new UnguessableCode();
        $code->setRemainingHits(0);

        $this->assertTrue($code->hasNoRemainingHits());
    }

    public function testHasRemainingHitsWhenPositive(): void
    {
        $code = new UnguessableCode();
        $code->setRemainingHits(3);

        $this->assertFalse($code->hasNoRemainingHits());
    }

    public function testHasRemainingHitsWhenNull(): void
    {
        $code = new UnguessableCode();

        $this->assertFalse($code->hasNoRemainingHits());
    }

    public function testContextJsonRoundtrip(): void
    {
        $code = new UnguessableCode();
        $code->setContext(['phone' => '+33612345678', 'code' => '123456']);

        $this->assertSame(['phone' => '+33612345678', 'code' => '123456'], $code->getContext());
    }

    public function testContextDefaultsToEmptyArray(): void
    {
        $code = new UnguessableCode();

        $this->assertSame([], $code->getContext());
    }
}
