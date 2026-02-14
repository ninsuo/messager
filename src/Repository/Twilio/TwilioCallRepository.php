<?php

namespace App\Repository\Twilio;

use App\Entity\Twilio\TwilioCall;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends AbstractTwilioRepository<TwilioCall>
 */
class TwilioCallRepository extends AbstractTwilioRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, TwilioCall::class);
    }
}
