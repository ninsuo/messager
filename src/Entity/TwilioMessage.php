<?php

namespace App\Entity;

use App\Repository\TwilioMessageRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: TwilioMessageRepository::class)]
#[ORM\Table]
#[ORM\UniqueConstraint(name: 'uuid_idx', columns: ['uuid'])]
#[ORM\Index(name: 'sid_idx', columns: ['sid'])]
#[ORM\Index(name: 'blind_from_number_idx', columns: ['blind_from_number'])]
#[ORM\Index(name: 'blind_to_number_idx', columns: ['blind_to_number'])]
#[ORM\HasLifecycleCallbacks]
class TwilioMessage extends AbstractTwilioEntity
{
    public const TYPE = 'message';

    public function getType(): string
    {
        return self::TYPE;
    }
}
