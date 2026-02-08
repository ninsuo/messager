<?php

namespace App\Provider\SMS;

use App\Entity\FakeSms;
use App\Repository\FakeSmsRepository;

readonly class FakeSmsProvider implements SmsProvider
{
    public function __construct(
        private FakeSmsRepository $fakeSmsRepository,
    ) {
    }

    public function send(string $from, string $to, string $message, array $context = []): ?string
    {
        $fakeSms = new FakeSms();
        $fakeSms->setFromNumber($from);
        $fakeSms->setToNumber($to);
        $fakeSms->setMessage($message);
        $fakeSms->setDirection(FakeSms::DIRECTION_SENT);

        $this->fakeSmsRepository->save($fakeSms);

        return sprintf('FAKE-%s', bin2hex(random_bytes(8)));
    }
}
