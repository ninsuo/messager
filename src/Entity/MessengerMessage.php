<?php

namespace App\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * Schema-only entity for the Symfony Messenger Doctrine transport table.
 * Managed by Messenger internally — no application code reads/writes these properties.
 */
#[ORM\Entity]
#[ORM\Table(name: 'messenger_messages')]
#[ORM\Index(name: 'queue_name_idx', columns: ['queue_name'])]
#[ORM\Index(name: 'available_at_idx', columns: ['available_at'])]
#[ORM\Index(name: 'delivered_at_idx', columns: ['delivered_at'])]
class MessengerMessage
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::BIGINT)]
    private ?string $id = null; // @phpstan-ignore property.unusedType, property.onlyWritten

    #[ORM\Column(type: Types::TEXT)]
    private ?string $body = null; // @phpstan-ignore property.unusedType, property.onlyWritten

    #[ORM\Column(type: Types::TEXT)]
    private ?string $headers = null; // @phpstan-ignore property.unusedType, property.onlyWritten

    #[ORM\Column(length: 190)]
    private ?string $queueName = null; // @phpstan-ignore property.unusedType, property.onlyWritten

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private ?\DateTimeImmutable $createdAt = null; // @phpstan-ignore property.unusedType, property.onlyWritten

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private ?\DateTimeImmutable $availableAt = null; // @phpstan-ignore property.unusedType, property.onlyWritten

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $deliveredAt = null; // @phpstan-ignore property.unusedType, property.onlyWritten
}
