<?php

namespace App\DataFixtures;

use App\Entity\FakeCall;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;

class FakeCallFixture extends Fixture
{
    public const CALL_ESTABLISH_1 = 'fake-call-establish-1';
    public const CALL_ESTABLISH_2 = 'fake-call-establish-2';
    public const CALL_KEY_PRESS_1 = 'fake-call-key-press-1';

    public function load(ObjectManager $manager): void
    {
        $establish1 = new FakeCall();
        $establish1->setFromNumber('+33612345678');
        $establish1->setToNumber('+33698765432');
        $establish1->setType(FakeCall::TYPE_ESTABLISH);
        $establish1->setContent('<Response><Say>Bonjour</Say></Response>');
        $manager->persist($establish1);
        $this->addReference(self::CALL_ESTABLISH_1, $establish1);

        $establish2 = new FakeCall();
        $establish2->setFromNumber('+33612345678');
        $establish2->setToNumber('+33611223344');
        $establish2->setType(FakeCall::TYPE_ESTABLISH);
        $manager->persist($establish2);
        $this->addReference(self::CALL_ESTABLISH_2, $establish2);

        $keyPress1 = new FakeCall();
        $keyPress1->setFromNumber('+33612345678');
        $keyPress1->setToNumber('+33698765432');
        $keyPress1->setType(FakeCall::TYPE_KEY_PRESS);
        $keyPress1->setContent('<Response><Say>Vous avez appuye sur 1</Say></Response>');
        $manager->persist($keyPress1);
        $this->addReference(self::CALL_KEY_PRESS_1, $keyPress1);

        $manager->flush();
    }
}
