<?php

namespace App\Entity;

use App\Repository\TwilioCallRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: TwilioCallRepository::class)]
#[ORM\Table]
#[ORM\UniqueConstraint(name: 'uuid_idx', columns: ['uuid'])]
#[ORM\Index(name: 'sid_idx', columns: ['sid'])]
#[ORM\Index(name: 'price_idx', columns: ['price'])]
#[ORM\HasLifecycleCallbacks]
class TwilioCall extends AbstractTwilioEntity
{
    public const TYPE = 'call';

    #[ORM\Column(type: 'datetime', nullable: true)]
    private ?\DateTime $startedAt = null;

    #[ORM\Column(type: 'datetime', nullable: true)]
    private ?\DateTime $endedAt = null;

    #[ORM\Column(nullable: true)]
    private ?int $duration = null;

    public function getStartedAt(): ?\DateTime
    {
        return $this->startedAt;
    }

    public function setStartedAt(\DateTime $startedAt): static
    {
        $this->startedAt = $startedAt;

        return $this;
    }

    public function getEndedAt(): ?\DateTime
    {
        return $this->endedAt;
    }

    public function setEndedAt(\DateTime $endedAt): static
    {
        $this->endedAt = $endedAt;

        return $this;
    }

    public function getDuration(): ?int
    {
        return $this->duration;
    }

    public function setDuration(int $duration): static
    {
        $this->duration = $duration;

        return $this;
    }

    public function getType(): string
    {
        return self::TYPE;
    }
}
