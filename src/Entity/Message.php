<?php

namespace App\Entity;

use App\Repository\MessageRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: MessageRepository::class)]
#[ORM\Table]
#[ORM\UniqueConstraint(name: 'uuid_idx', columns: ['uuid'])]
#[ORM\HasLifecycleCallbacks]
class Message
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_SENT = 'sent';
    public const STATUS_DELIVERED = 'delivered';
    public const STATUS_FAILED = 'failed';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null; // @phpstan-ignore property.unusedType

    #[ORM\Column(length: 36)]
    private ?string $uuid = null;

    #[ORM\ManyToOne(targetEntity: Trigger::class, inversedBy: 'messages')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Trigger $trigger = null;

    #[ORM\ManyToOne(targetEntity: Contact::class)]
    #[ORM\JoinColumn(nullable: false)]
    private ?Contact $contact = null;

    #[ORM\Column(length: 20)]
    private ?string $status = self::STATUS_PENDING; // @phpstan-ignore property.unusedType

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $error = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTime $createdAt = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUuid(): string
    {
        return $this->uuid ?? '';
    }

    public function setUuid(string $uuid): static
    {
        $this->uuid = $uuid;

        return $this;
    }

    public function getTrigger(): ?Trigger
    {
        return $this->trigger;
    }

    public function setTrigger(Trigger $trigger): static
    {
        $this->trigger = $trigger;

        return $this;
    }

    public function getContact(): ?Contact
    {
        return $this->contact;
    }

    public function setContact(Contact $contact): static
    {
        $this->contact = $contact;

        return $this;
    }

    public function getStatus(): ?string
    {
        return $this->status;
    }

    public function setStatus(string $status): static
    {
        $this->status = $status;

        return $this;
    }

    public function getError(): ?string
    {
        return $this->error;
    }

    public function setError(?string $error): static
    {
        $this->error = $error;

        return $this;
    }

    public function getCreatedAt(): ?\DateTime
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTime $createdAt): static
    {
        $this->createdAt = $createdAt;

        return $this;
    }

    #[ORM\PrePersist]
    public function onPrePersist(): void
    {
        $this->setCreatedAt(new \DateTime());
    }
}
