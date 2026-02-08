<?php

namespace App\DataFixtures;

use App\Entity\Contact;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\Uid\Uuid;

class ContactFixture extends Fixture
{
    public const CONTACT_1 = 'contact-1';
    public const CONTACT_2 = 'contact-2';
    public const CONTACT_3 = 'contact-3';
    public const CONTACT_4 = 'contact-4';
    public const CONTACT_5 = 'contact-5';

    public function load(ObjectManager $manager): void
    {
        $phones = [
            self::CONTACT_1 => '+33611111111',
            self::CONTACT_2 => '+33622222222',
            self::CONTACT_3 => '+33633333333',
            self::CONTACT_4 => '+33644444444',
            self::CONTACT_5 => '+33655555555',
        ];

        foreach ($phones as $ref => $phone) {
            $contact = new Contact();
            $contact->setUuid(Uuid::v4()->toRfc4122());
            $contact->setPhoneNumber($phone);
            $manager->persist($contact);
            $this->addReference($ref, $contact);
        }

        $manager->flush();
    }
}
