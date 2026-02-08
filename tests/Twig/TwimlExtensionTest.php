<?php

namespace App\Tests\Twig;

use App\Twig\TwimlExtension;
use PHPUnit\Framework\TestCase;

class TwimlExtensionTest extends TestCase
{
    private TwimlExtension $extension;

    protected function setUp(): void
    {
        $this->extension = new TwimlExtension();
    }

    public function testNullReturnsEmpty(): void
    {
        $this->assertSame('', $this->extension->twimlToText(null));
    }

    public function testEmptyStringReturnsEmpty(): void
    {
        $this->assertSame('', $this->extension->twimlToText(''));
    }

    public function testSimpleSayExtraction(): void
    {
        $twiml = '<Response><Say>Bonjour</Say></Response>';

        $this->assertSame('Bonjour', $this->extension->twimlToText($twiml));
    }

    public function testNestedGatherSayExtraction(): void
    {
        $twiml = '<Response><Gather><Say>Appuyez sur 1</Say></Gather></Response>';

        $this->assertSame('Appuyez sur 1', $this->extension->twimlToText($twiml));
    }

    public function testMultipleSayElements(): void
    {
        $twiml = '<Response><Say>Bonjour</Say><Gather><Say>Appuyez sur 1</Say></Gather></Response>';

        $this->assertSame('Bonjour Appuyez sur 1', $this->extension->twimlToText($twiml));
    }

    public function testInvalidXmlReturnsInput(): void
    {
        $input = 'not valid xml <>';

        $this->assertSame($input, $this->extension->twimlToText($input));
    }

    public function testFilterIsRegistered(): void
    {
        $filters = $this->extension->getFilters();
        $names = array_map(fn ($f) => $f->getName(), $filters);

        $this->assertContains('twiml_to_text', $names);
    }
}
