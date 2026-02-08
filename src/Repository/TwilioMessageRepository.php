<?php

namespace App\Repository;

use App\Entity\TwilioMessage;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends AbstractTwilioRepository<TwilioMessage>
 */
class TwilioMessageRepository extends AbstractTwilioRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, TwilioMessage::class);
    }
}
