<?php

namespace App\Provider\SMS;

use App\Entity\FakeSms;
use App\Repository\FakeSmsRepository;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

readonly class FakeSmsProvider implements SmsProvider
{
    public function __construct(
        private FakeSmsRepository $fakeSmsRepository,
        #[Autowire(env: 'TWILIO_SENDER_ID')]
        private string $senderId,
    ) {
    }

    public function send(string $to, string $message, array $context = []): ?string
    {
        $fakeSms = new FakeSms();
        $fakeSms->setFromNumber($this->senderId);
        $fakeSms->setToNumber($to);
        $fakeSms->setMessage($message);
        $fakeSms->setDirection(FakeSms::DIRECTION_SENT);

        $this->fakeSmsRepository->save($fakeSms);

        return sprintf('FAKE-%s', bin2hex(random_bytes(8)));
    }
}
