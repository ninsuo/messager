<?php

namespace App\Contract;

/**
 * Entities with encrypted fields must implement this interface.
 * The UUID is used as additional authenticated data for AES encryption.
 */
interface EncryptedResourceInterface
{
    public function getUuid(): string;

    public function getId(): ?int;
}
