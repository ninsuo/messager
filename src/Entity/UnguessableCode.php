<?php

namespace App\Entity;

use App\Attribute\Encrypted;
use App\Contract\EncryptedResourceInterface;
use App\Repository\UnguessableCodeRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: UnguessableCodeRepository::class)]
#[ORM\Table]
#[ORM\UniqueConstraint(name: 'code_idx', columns: ['code'])]
#[ORM\HasLifecycleCallbacks]
class UnguessableCode implements EncryptedResourceInterface
{
    public const SECRET_LENGTH = 32;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null; // @phpstan-ignore property.unusedType

    #[ORM\Column(length: 36)]
    private ?string $uuid = null;

    #[ORM\Column(length: 64, unique: true)]
    private ?string $code = null;

    #[ORM\Column(length: 255)]
    private ?string $purpose = null;

    #[Encrypted]
    #[ORM\Column(type: Types::TEXT)]
    private ?string $encryptedContext = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $expiresAt = null;

    #[ORM\Column(nullable: true)]
    private ?int $remainingHits = null;

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

    public function getCode(): ?string
    {
        return $this->code;
    }

    public function setCode(?string $code): static
    {
        $this->code = $code;

        return $this;
    }

    public function getPurpose(): ?string
    {
        return $this->purpose;
    }

    public function setPurpose(?string $purpose): static
    {
        $this->purpose = $purpose;

        return $this;
    }

    public function getEncryptedContext(): ?string
    {
        return $this->encryptedContext;
    }

    public function setEncryptedContext(?string $encryptedContext): static
    {
        $this->encryptedContext = $encryptedContext;

        return $this;
    }

    /**
     * @return array<string, mixed>
     */
    public function getContext(): array
    {
        return json_decode((string) $this->encryptedContext, true) ?: [];
    }

    /**
     * @param array<string, mixed> $context
     */
    public function setContext(array $context): static
    {
        $this->encryptedContext = json_encode($context);

        return $this;
    }

    public function getExpiresAt(): ?\DateTimeImmutable
    {
        return $this->expiresAt;
    }

    public function setExpiresAt(?\DateTimeImmutable $expiresAt): static
    {
        $this->expiresAt = $expiresAt;

        return $this;
    }

    public function getRemainingHits(): ?int
    {
        return $this->remainingHits;
    }

    public function setRemainingHits(?int $remainingHits): static
    {
        $this->remainingHits = $remainingHits;

        return $this;
    }

    public function hasExpired(): bool
    {
        return null !== $this->expiresAt && $this->expiresAt->getTimestamp() < time();
    }

    public function hasNoRemainingHits(): bool
    {
        return 0 === $this->remainingHits;
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
