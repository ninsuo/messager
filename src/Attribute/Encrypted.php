<?php

namespace App\Attribute;

/**
 * Marks an entity property as encrypted at rest.
 *
 * The property value is transparently encrypted before persist and
 * decrypted after load by EncryptedResourceSubscriber.
 * The owning entity must implement EncryptedResourceInterface.
 */
#[\Attribute(\Attribute::TARGET_PROPERTY)]
final readonly class Encrypted
{
}
