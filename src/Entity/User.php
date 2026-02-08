<?php

namespace App\Entity;

use App\Attribute\BlindIndex;
use App\Attribute\Encrypted;
use App\Contract\EncryptedResourceInterface;
use App\Repository\UserRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Security\Core\User\UserInterface;

#[ORM\Entity(repositoryClass: UserRepository::class)]
#[ORM\Table(name: '`user`')]
#[ORM\UniqueConstraint(name: 'uuid_idx', columns: ['uuid'])]
#[ORM\Index(name: 'blind_phone_number_idx', columns: ['blind_phone_number'])]
#[ORM\HasLifecycleCallbacks]
class User implements UserInterface, EncryptedResourceInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null; // @phpstan-ignore property.unusedType

    #[ORM\Column(length: 36)]
    private ?string $uuid = null;

    #[Encrypted]
    #[ORM\Column(type: Types::TEXT)]
    private ?string $phoneNumber = null;

    #[BlindIndex('phoneNumber')]
    #[ORM\Column(length: 64)]
    private ?string $blindPhoneNumber = null;

    #[ORM\Column]
    private bool $isAdmin = false;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTime $createdAt = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTime $updatedAt = null;

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

    public function getPhoneNumber(): ?string
    {
        return $this->phoneNumber;
    }

    public function setPhoneNumber(string $phoneNumber): static
    {
        $this->phoneNumber = $phoneNumber;

        return $this;
    }

    public function getBlindPhoneNumber(): ?string
    {
        return $this->blindPhoneNumber;
    }

    public function setBlindPhoneNumber(?string $blindPhoneNumber): static
    {
        $this->blindPhoneNumber = $blindPhoneNumber;

        return $this;
    }

    public function isAdmin(): bool
    {
        return $this->isAdmin;
    }

    public function setIsAdmin(bool $isAdmin): static
    {
        $this->isAdmin = $isAdmin;

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

    public function getUserIdentifier(): string
    {
        return $this->getUuid();
    }

    /**
     * @return list<string>
     */
    public function getRoles(): array
    {
        $roles = ['ROLE_USER'];

        if ($this->isAdmin) {
            array_unshift($roles, 'ROLE_ADMIN');
        }

        return $roles;
    }

    public function eraseCredentials(): void
    {
    }
}
