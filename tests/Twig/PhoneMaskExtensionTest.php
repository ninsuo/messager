<?php

namespace App\Tests\Twig;

use App\Twig\PhoneMaskExtension;
use PHPUnit\Framework\TestCase;

class PhoneMaskExtensionTest extends TestCase
{
    private PhoneMaskExtension $extension;

    protected function setUp(): void
    {
        $this->extension = new PhoneMaskExtension();
    }

    public function testStandardPhoneIsMasked(): void
    {
        $this->assertSame('+336XXXX1234', $this->extension->maskPhone('+33612341234'));
    }

    public function testShortPhoneIsReturnedAsIs(): void
    {
        $this->assertSame('+3361234', $this->extension->maskPhone('+3361234'));
    }

    public function testExactlyNineCharsIsMasked(): void
    {
        $this->assertSame('+336X1234', $this->extension->maskPhone('+33611234'));
    }

    public function testLongInternationalNumber(): void
    {
        $this->assertSame('+441XXXXXXXX6789', $this->extension->maskPhone('+441234567896789'));
    }

    public function testFilterIsRegistered(): void
    {
        $filters = $this->extension->getFilters();
        $names = array_map(fn ($f) => $f->getName(), $filters);

        $this->assertContains('mask_phone', $names);
    }
}
