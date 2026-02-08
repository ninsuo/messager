<?php

namespace App\Repository;

use App\Entity\TwilioCall;
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
