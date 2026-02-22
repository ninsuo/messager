<?php

namespace App\DataFixtures\Fake;

use App\Entity\Fake\FakeCall;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;

class FakeCallFixture extends Fixture
{
    public const CALL_1 = 'fake-call-1';
    public const CALL_2 = 'fake-call-2';

    public function load(ObjectManager $manager): void
    {
        $call1 = new FakeCall();
        $call1->setFromNumber('+33612345678');
        $call1->setToNumber('+33698765432');
        $call1->setType(FakeCall::TYPE_ESTABLISH);
        $call1->setContent('<Response><Say>Bonjour</Say></Response>');
        $manager->persist($call1);
        $this->addReference(self::CALL_1, $call1);

        $call2 = new FakeCall();
        $call2->setFromNumber('+33612345678');
        $call2->setToNumber('+33611223344');
        $call2->setType(FakeCall::TYPE_ESTABLISH);
        $manager->persist($call2);
        $this->addReference(self::CALL_2, $call2);

        $manager->flush();
    }
}
