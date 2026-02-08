<?php

namespace App\Attribute;

/**
 * Marks a property as a blind index for an #[Encrypted] field.
 *
 * The blind index column stores an HMAC hash of the cleartext value,
 * allowing findOneBy() queries on encrypted data without decryption.
 *
 * The $field parameter references the encrypted property this index maps to.
 */
#[\Attribute(\Attribute::TARGET_PROPERTY)]
final readonly class BlindIndex
{
    public function __construct(private string $field)
    {
    }

    public function getField(): string
    {
        return $this->field;
    }
}
