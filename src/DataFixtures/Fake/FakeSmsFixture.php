<?php

namespace App\DataFixtures\Fake;

use App\Entity\Fake\FakeSms;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;

class FakeSmsFixture extends Fixture
{
    public const SMS_SENT_1 = 'fake-sms-sent-1';
    public const SMS_SENT_2 = 'fake-sms-sent-2';
    public const SMS_RECEIVED_1 = 'fake-sms-received-1';

    public function load(ObjectManager $manager): void
    {
        $sent1 = new FakeSms();
        $sent1->setFromNumber('+33612345678');
        $sent1->setToNumber('+33698765432');
        $sent1->setMessage('Bonjour, ceci est un test.');
        $sent1->setDirection(FakeSms::DIRECTION_SENT);
        $manager->persist($sent1);
        $this->addReference(self::SMS_SENT_1, $sent1);

        $sent2 = new FakeSms();
        $sent2->setFromNumber('+33612345678');
        $sent2->setToNumber('+33611223344');
        $sent2->setMessage('Second message de test.');
        $sent2->setDirection(FakeSms::DIRECTION_SENT);
        $manager->persist($sent2);
        $this->addReference(self::SMS_SENT_2, $sent2);

        $received1 = new FakeSms();
        $received1->setFromNumber('+33698765432');
        $received1->setToNumber('+33612345678');
        $received1->setMessage('Bien recu, merci !');
        $received1->setDirection(FakeSms::DIRECTION_RECEIVED);
        $manager->persist($received1);
        $this->addReference(self::SMS_RECEIVED_1, $received1);

        $manager->flush();
    }
}
