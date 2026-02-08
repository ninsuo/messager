<?php

namespace App\DataFixtures;

use App\Entity\Contact;
use App\Entity\Message;
use App\Entity\Trigger;
use App\Entity\User;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\Uid\Uuid;

class TriggerFixture extends Fixture implements DependentFixtureInterface
{
    public const TRIGGER_SMS = 'trigger-sms';
    public const TRIGGER_CALL = 'trigger-call';
    public const TRIGGER_WITH_ERRORS = 'trigger-with-errors';

    public function load(ObjectManager $manager): void
    {
        $admin = $this->getReference(UserFixture::USER_ADMIN, User::class);
        $regular = $this->getReference(UserFixture::USER_REGULAR, User::class);

        $contact1 = $this->getReference(ContactFixture::CONTACT_1, Contact::class);
        $contact2 = $this->getReference(ContactFixture::CONTACT_2, Contact::class);
        $contact3 = $this->getReference(ContactFixture::CONTACT_3, Contact::class);
        $contact4 = $this->getReference(ContactFixture::CONTACT_4, Contact::class);
        $contact5 = $this->getReference(ContactFixture::CONTACT_5, Contact::class);

        // SMS trigger by admin — all messages sent successfully
        $smsTrigger = new Trigger();
        $smsTrigger->setUuid(Uuid::v4()->toRfc4122());
        $smsTrigger->setUser($admin);
        $smsTrigger->setType(Trigger::TYPE_SMS);
        $smsTrigger->setContent('Bonjour, reunion demain a 14h. Merci de confirmer.');
        $smsTrigger->addContact($contact1);
        $smsTrigger->addContact($contact2);
        $smsTrigger->addContact($contact3);

        foreach ([$contact1, $contact2, $contact3] as $contact) {
            $message = new Message();
            $message->setUuid(Uuid::v4()->toRfc4122());
            $message->setContact($contact);
            $message->setStatus(Message::STATUS_SENT);
            $smsTrigger->addMessage($message);
        }

        $manager->persist($smsTrigger);
        $this->addReference(self::TRIGGER_SMS, $smsTrigger);

        // Call trigger by admin — all messages sent successfully
        $callTrigger = new Trigger();
        $callTrigger->setUuid(Uuid::v4()->toRfc4122());
        $callTrigger->setUser($admin);
        $callTrigger->setType(Trigger::TYPE_CALL);
        $callTrigger->setContent('Alerte meteo : vigilance orange sur le departement.');
        $callTrigger->addContact($contact1);
        $callTrigger->addContact($contact4);

        foreach ([$contact1, $contact4] as $contact) {
            $message = new Message();
            $message->setUuid(Uuid::v4()->toRfc4122());
            $message->setContact($contact);
            $message->setStatus(Message::STATUS_SENT);
            $callTrigger->addMessage($message);
        }

        $manager->persist($callTrigger);
        $this->addReference(self::TRIGGER_CALL, $callTrigger);

        // SMS trigger by regular user — with some errors
        $errorTrigger = new Trigger();
        $errorTrigger->setUuid(Uuid::v4()->toRfc4122());
        $errorTrigger->setUser($regular);
        $errorTrigger->setType(Trigger::TYPE_SMS);
        $errorTrigger->setContent('Rappel : inscription avant vendredi.');
        $errorTrigger->addContact($contact3);
        $errorTrigger->addContact($contact4);
        $errorTrigger->addContact($contact5);

        $msgOk = new Message();
        $msgOk->setUuid(Uuid::v4()->toRfc4122());
        $msgOk->setContact($contact3);
        $msgOk->setStatus(Message::STATUS_SENT);
        $errorTrigger->addMessage($msgOk);

        $msgErr1 = new Message();
        $msgErr1->setUuid(Uuid::v4()->toRfc4122());
        $msgErr1->setContact($contact4);
        $msgErr1->setStatus(Message::STATUS_FAILED);
        $msgErr1->setError('Undeliverable: invalid number');
        $errorTrigger->addMessage($msgErr1);

        $msgErr2 = new Message();
        $msgErr2->setUuid(Uuid::v4()->toRfc4122());
        $msgErr2->setContact($contact5);
        $msgErr2->setStatus(Message::STATUS_FAILED);
        $msgErr2->setError('Carrier rejected');
        $errorTrigger->addMessage($msgErr2);

        $manager->persist($errorTrigger);
        $this->addReference(self::TRIGGER_WITH_ERRORS, $errorTrigger);

        $manager->flush();
    }

    public function getDependencies(): array
    {
        return [
            UserFixture::class,
            ContactFixture::class,
        ];
    }
}
