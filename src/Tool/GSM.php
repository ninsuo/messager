<?php

namespace App\Tool;

class GSM
{
    public const ALPHABET = [
        'A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'I', 'J', 'K', 'L', 'M',
        'N', 'O', 'P', 'Q', 'R', 'S', 'T', 'U', 'V', 'W', 'X', 'Y', 'Z',
        'a', 'b', 'c', 'd', 'e', 'f', 'g', 'h', 'i', 'j', 'k', 'l', 'm',
        'n', 'o', 'p', 'q', 'r', 's', 't', 'u', 'v', 'w', 'x', 'y', 'z',
        '0', '1', '2', '3', '4', '5', '6', '7', '8', '9',
        '!', '#', ' ', '"', '%', '&', '\'', '(', ')', '*', ',', '.', '?',
        '+', '-', '/', ';', ':', '<', '=', '>',
        '¡', '¿', '_', '@', '§', '$', '£', '¥',
        'è', 'é', 'ù', 'ì', 'ò', 'Ç', 'Ø', 'ø', 'Æ', 'æ', 'ß', 'É',
        'Å', 'å', 'Ä', 'Ö', 'Ñ', 'Ü', 'ä', 'ö', 'ñ', 'ü', 'à',
        "\n",
        'Δ', 'Φ', 'Γ', 'Λ', 'Ω', 'Π', 'Ψ', 'Σ', 'Θ', 'Ξ',
        '¤', '€',
        '[', ']', '{', '}', '\\', '^', '~', '|',
    ];

    public const ESCAPED = [
        '€', '\\', '^', '|',
    ];

    /** @var array<string, string> */
    public const TRANSLITERATION = [
        '/[ \t]{2,}/'                         => ' ',
        '/\r\n|\r/'                           => "\n",
        '/ /'                                 => ' ',
        '/–|—|~/'                             => '-',
        '/\[|\{/'                             => '(',
        '/\]|\}/'                             => ')',
        '/₹/'                                 => 'Rs',
        '/₴/'                                 => 'UAH',
        '/₽/'                                 => 'p',
        '/·/'                                 => '.',
        '/ѣ|Ѣ|́|Ь|ь|Ъ|ъ/'                     => '',
        '/º|°/'                               => '0',
        '/¹/'                                 => '1',
        '/²/'                                 => '2',
        '/³/'                                 => '3',
        '/ǽ/'                                 => 'ae',
        '/œ/'                                 => 'oe',
        '/À|Á|Â|Ã|Ǻ|Ā|Ă|Ą|Ǎ|А|Α/'             => 'A',
        '/á|â|ã|ǻ|ā|ă|ą|ǎ|ª|а/'               => 'a',
        '/Б/'                                 => 'B',
        '/б/'                                 => 'b',
        '/Ç|Ć|Ĉ|Ċ|Č|Ћ/'                       => 'C',
        '/ç|ć|ĉ|ċ|č|ћ/'                       => 'c',
        '/Д/'                                 => 'D',
        '/д/'                                 => 'd',
        '/Ð|Ď|Đ|Ђ/'                           => 'Dj',
        '/ð|ď|đ|ђ/'                           => 'dj',
        '/È|Ê|Ë|Ē|Ĕ|Ė|Ę|Ě|Е|Ё|ЬЭ|Э|Є|Ѧ|Ễ/'    => 'E',
        '/ê|ë|ē|ĕ|ė|ę|ě|е|ё|ьэ|э|є|ѧ|ə|ɘ|ễ/'  => 'e',
        '/Ф/'                                 => 'F',
        '/ƒ|ф/'                               => 'f',
        '/Ĝ|Ğ|Ġ|Ģ|Г|Ґ/'                       => 'G',
        '/ĝ|ğ|ġ|ģ|г|ґ/'                       => 'g',
        '/Ĥ|Ħ/'                               => 'H',
        '/ĥ|ħ/'                               => 'h',
        '/Ì|Í|Î|Ï|Ĩ|Ī|Ĭ|Ǐ|Į|İ|И|Й|І/'         => 'I',
        '/í|î|ï|ĩ|ī|ĭ|ǐ|į|ı|и|й|і/'           => 'i',
        '/Ĵ/'                                 => 'J',
        '/ĵ/'                                 => 'J',
        '/Ķ|К/'                               => 'K',
        '/ķ|к/'                               => 'k',
        '/Х/'                                 => 'Kh',
        '/х/'                                 => 'kh',
        '/Ĺ|Ļ|Ľ|Ŀ|Ł|Л/'                       => 'L',
        '/ĺ|ļ|ľ|ŀ|ł|л/'                       => 'l',
        '/М/'                                 => 'M',
        '/м/'                                 => 'm',
        '/Ń|Ņ|Ň|Н|№/'                         => 'N',
        '/ń|ņ|ň|ŉ|н/'                         => 'n',
        '/Ò|Ó|Ô|Õ|Ō|Ŏ|Ǒ|Ő|Ơ|Ǿ|О|Ѡ|Ѫ|Ờ/'       => 'O',
        '/ó|ô|õ|ō|ŏ|ǒ|ő|ơ|ǿ|º|о|ѡ|ѫ|ờ/'       => 'o',
        '/П/'                                 => 'P',
        '/п/'                                 => 'p',
        '/Ŕ|Ŗ|Ř|Р/'                           => 'R',
        '/ŕ|ŗ|ř|р/'                           => 'r',
        '/Ś|Ŝ|Ş|Ș|Š|С/'                       => 'S',
        '/ś|ŝ|ş|ș|š|ſ|с/'                     => 's',
        '/Ţ|Ț|Ť|Ŧ|Т/'                         => 'T',
        '/ţ|ț|ť|ŧ|т/'                         => 't',
        '/Ц/'                                 => 'Tc',
        '/ц/'                                 => 'tc',
        '/Ù|Ú|Û|Ũ|Ū|Ŭ|Ů|Ű|Ų|Ư|Ǔ|Ǖ|Ǘ|Ǚ|Ǜ|У|Ў/' => 'U',
        '/ú|û|ũ|ū|ŭ|ů|ű|ų|ư|ǔ|ǖ|ǘ|ǚ|ǜ|у|ў/'   => 'u',
        '/В/'                                 => 'V',
        '/в/'                                 => 'v',
        '/Ý|Ÿ|Ŷ|Ỳ|Ы/'                         => 'Y',
        '/ý|ÿ|ŷ|ỳ|ы/'                         => 'y',
        '/Ŵ/'                                 => 'W',
        '/ŵ/'                                 => 'w',
        '/Ź|Ż|Ž|З/'                           => 'Z',
        '/ź|ż|ž|з/'                           => 'z',
        '/Ǽ/'                                 => 'AE',
        '/Ĳ/'                                 => 'IJ',
        '/ĳ/'                                 => 'ij',
        '/Œ/'                                 => 'OE',
        '/Ч/'                                 => 'Ch',
        '/ч/'                                 => 'ch',
        '/Ю/'                                 => 'Iu',
        '/ю/'                                 => 'iu',
        '/Я/'                                 => 'Ia',
        '/я/'                                 => 'ia',
        '/Ї/'                                 => 'Ji',
        '/ї/'                                 => 'ji',
        '/Ш/'                                 => 'Sh',
        '/ш/'                                 => 'sh',
        '/Щ/'                                 => 'Shch',
        '/щ/'                                 => 'shch',
        '/Ж/'                                 => 'Zh',
        '/ж/'                                 => 'zh',
        '/ѕ|џ/'                               => 'dz',
        '/Ѕ|Џ/'                               => 'Dz',
        '/ј/'                                 => 'j',
        '/љ/'                                 => 'lj',
        '/Љ/'                                 => 'Lj',
        '/њ/'                                 => 'nj',
        '/Њ/'                                 => 'Nj',
        '/ќ/'                                 => 'kj',
        '/Ќ/'                                 => 'Kj',
        '/ѩ/'                                 => 'je',
        '/Ѩ/'                                 => 'Je',
        '/ѭ/'                                 => 'jo',
        '/Ѭ/'                                 => 'Jo',
        '/ѯ/'                                 => 'ks',
        '/Ѯ/'                                 => 'Ks',
        '/ѱ/'                                 => 'ps',
        '/Ѱ/'                                 => 'Ps',
        '/ѥ/'                                 => 'je',
        '/Ѥ/'                                 => 'Je',
        '/ꙗ/'                                 => 'ja',
        '/Ꙗ/'                                 => 'ja',
        '/«|»/'                               => '"',
        '/\x{2019}|`/u'                       => '\'',
    ];

    public static function isGSMCompatible(string $message): bool
    {
        foreach (mb_str_split($message) as $letter) {
            if (!in_array($letter, self::ALPHABET, true)) {
                return false;
            }
        }

        return true;
    }

    public static function transliterate(string $message): string
    {
        return trim((string) preg_replace(
            array_keys(self::TRANSLITERATION),
            array_values(self::TRANSLITERATION),
            $message
        ));
    }

    public static function enforceGSMAlphabet(string $message): string
    {
        $sanitized = '';
        foreach (mb_str_split(self::transliterate($message)) as $letter) {
            $sanitized .= in_array($letter, self::ALPHABET, true) ? $letter : '?';
        }

        return $sanitized;
    }

    /**
     * @return list<string>
     */
    public static function getSMSParts(string $message): array
    {
        $unicode = !self::isGSMCompatible($message);

        $length = 0;
        foreach (mb_str_split($message) as $letter) {
            if (!$unicode && in_array($letter, self::ESCAPED, true)) {
                ++$length;
            }
            ++$length;
        }

        $multipart = (!$unicode && $length > 160) || ($unicode && $length > 70);

        if (!$multipart) {
            return [$message];
        }

        $partLimit = $unicode ? 67 : 153;
        $parts = [];
        $part = '';
        $length = 0;

        foreach (mb_str_split($message) as $letter) {
            $charSize = (!$unicode && in_array($letter, self::ESCAPED, true)) ? 2 : 1;

            if ($length + $charSize > $partLimit) {
                $parts[] = $part;
                $part = '';
                $length = 0;
            }

            $part .= $letter;
            $length += $charSize;
        }

        if ('' !== $part) {
            $parts[] = $part;
        }

        return $parts;
    }
}
