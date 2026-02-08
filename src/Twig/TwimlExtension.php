<?php

namespace App\Twig;

use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;

class TwimlExtension extends AbstractExtension
{
    public function getFilters(): array
    {
        return [
            new TwigFilter('twiml_to_text', $this->twimlToText(...)),
        ];
    }

    public function twimlToText(?string $twiml): string
    {
        if ($twiml === null || $twiml === '') {
            return '';
        }

        $previous = libxml_use_internal_errors(true);

        try {
            $xml = simplexml_load_string($twiml);

            if ($xml === false) {
                return $twiml;
            }

            $texts = [];
            $this->extractSayTexts($xml, $texts);

            return implode(' ', $texts);
        } finally {
            libxml_use_internal_errors($previous);
        }
    }

    /**
     * @param list<string> $texts
     */
    private function extractSayTexts(\SimpleXMLElement $element, array &$texts): void
    {
        if ($element->getName() === 'Say') {
            $text = trim((string) $element);
            if ($text !== '') {
                $texts[] = $text;
            }
        }

        foreach ($element->children() as $child) {
            $this->extractSayTexts($child, $texts);
        }
    }
}
