<?php

namespace App\Entity\Twilio;

use App\Attribute\BlindIndex;
use App\Attribute\Encrypted;
use App\Contract\EncryptedResourceInterface;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\MappedSuperclass]
#[ORM\HasLifecycleCallbacks]
abstract class AbstractTwilioEntity implements EncryptedResourceInterface
{
    public const DIRECTION_INBOUND = 'inbound';
    public const DIRECTION_OUTBOUND = 'outbound';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    protected ?int $id = null;

    #[ORM\Column(length: 36)]
    protected ?string $uuid = null;

    #[ORM\Column(length: 16)]
    protected ?string $direction = null;

    #[Encrypted]
    #[ORM\Column(type: Types::TEXT, nullable: true)]
    protected ?string $message = null;

    #[Encrypted]
    #[ORM\Column(type: Types::TEXT, nullable: true)]
    protected ?string $fromNumber = null;

    #[BlindIndex('fromNumber')]
    #[ORM\Column(length: 64, nullable: true)]
    protected ?string $blindFromNumber = null;

    #[Encrypted]
    #[ORM\Column(type: Types::TEXT, nullable: true)]
    protected ?string $toNumber = null;

    #[BlindIndex('toNumber')]
    #[ORM\Column(length: 64, nullable: true)]
    protected ?string $blindToNumber = null;

    #[ORM\Column(length: 64, nullable: true)]
    protected ?string $sid = null;

    #[ORM\Column(length: 20, nullable: true)]
    protected ?string $status = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    protected ?string $context = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    protected ?\DateTime $createdAt = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    protected ?\DateTime $updatedAt = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $error = null;

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

    public function getDirection(): ?string
    {
        return $this->direction;
    }

    public function setDirection(string $direction): static
    {
        $this->direction = $direction;

        return $this;
    }

    public function getMessage(): ?string
    {
        return $this->message;
    }

    public function setMessage(string $message): static
    {
        $this->message = $message;

        return $this;
    }

    public function getFromNumber(): ?string
    {
        return $this->fromNumber;
    }

    public function setFromNumber(?string $fromNumber): static
    {
        $this->fromNumber = $fromNumber;

        return $this;
    }

    public function getBlindFromNumber(): ?string
    {
        return $this->blindFromNumber;
    }

    public function setBlindFromNumber(?string $blindFromNumber): static
    {
        $this->blindFromNumber = $blindFromNumber;

        return $this;
    }

    public function getToNumber(): ?string
    {
        return $this->toNumber;
    }

    public function setToNumber(?string $toNumber): static
    {
        $this->toNumber = $toNumber;

        return $this;
    }

    public function getBlindToNumber(): ?string
    {
        return $this->blindToNumber;
    }

    public function setBlindToNumber(?string $blindToNumber): static
    {
        $this->blindToNumber = $blindToNumber;

        return $this;
    }

    public function getSid(): ?string
    {
        return $this->sid;
    }

    public function setSid(string $sid): static
    {
        $this->sid = $sid;

        return $this;
    }

    public function getStatus(): ?string
    {
        return $this->status;
    }

    public function setStatus(?string $status): static
    {
        $this->status = $status;

        return $this;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getContext(): ?array
    {
        return $this->context ? json_decode($this->context, true) : null;
    }

    public function setContext(mixed $context): static
    {
        $this->context = json_encode($context);

        return $this;
    }

    public function getError(): ?string
    {
        return $this->error;
    }

    public function setError(?string $error): static
    {
        $this->error = mb_substr($error, 0, 255);

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

    public function getUpdatedAt(): ?\DateTime
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(\DateTime $updatedAt): static
    {
        $this->updatedAt = $updatedAt;

        return $this;
    }

    #[ORM\PrePersist]
    public function onPrePersist(): void
    {
        $this->setCreatedAt(new \DateTime());
        $this->setUpdatedAt(new \DateTime());
    }

    #[ORM\PreUpdate]
    public function onPreUpdate(): void
    {
        $this->setUpdatedAt(new \DateTime());
    }

    abstract public function getType(): string;
}
