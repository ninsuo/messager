<?php

namespace App\EventSubscriber;

use App\Event\TwilioCallEvent;
use App\Event\TwilioEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Twilio\TwiML\VoiceResponse;

class AuthCodeSubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [
            TwilioEvent::CALL_ESTABLISHED => ['onCallEstablished', 10],
        ];
    }

    public function onCallEstablished(TwilioCallEvent $event): void
    {
        $context = $event->getCall()->getContext();
        $authCode = $context['auth_code'] ?? null;

        if (null === $authCode) {
            return;
        }

        $digits = str_split(str_replace(' ', '', $authCode));
        $spelled = implode(', ', $digits);

        $response = new VoiceResponse();

        for ($i = 0; $i < 3; $i++) {
            $response->say(
                sprintf('Votre code Messager est : %s.', $spelled),
                ['language' => 'fr-FR', 'voice' => 'Polly.Lea'],
            );

            if ($i < 2) {
                $response->pause(['length' => 2]);
                $response->say('Je répète.', [
                    'language' => 'fr-FR',
                    'voice' => 'Polly.Lea',
                ]);
                $response->pause(['length' => 2]);
            }
        }

        $event->setResponse($response);
    }
}
