<?php

namespace App\Repository\Twilio;

use App\Entity\Twilio\TwilioMessage;
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
