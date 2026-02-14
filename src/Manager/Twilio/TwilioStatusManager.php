<?php

namespace App\Manager\Twilio;

use App\Entity\Twilio\TwilioStatus;
use App\Repository\Twilio\TwilioStatusRepository;

class TwilioStatusManager
{
    public function __construct(
        private readonly TwilioStatusRepository $statusRepository,
    ) {
    }

    /**
     * @return TwilioStatus[]
     */
    public function getStatuses(string $sid): array
    {
        return $this->statusRepository->getStatuses($sid);
    }

    public function save(TwilioStatus $status): void
    {
        $this->statusRepository->save($status);
    }
}
