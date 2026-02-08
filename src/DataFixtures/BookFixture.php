<?php

namespace App\DataFixtures;

use App\Entity\Book;
use App\Entity\Contact;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\Uid\Uuid;

class BookFixture extends Fixture implements DependentFixtureInterface
{
    public const BOOK_FAMILY = 'book-family';
    public const BOOK_WORK = 'book-work';

    public function load(ObjectManager $manager): void
    {
        $family = new Book();
        $family->setUuid(Uuid::v4()->toRfc4122());
        $family->setName('Famille');
        $family->addContact($this->getReference(ContactFixture::CONTACT_1, Contact::class));
        $family->addContact($this->getReference(ContactFixture::CONTACT_2, Contact::class));
        $family->addContact($this->getReference(ContactFixture::CONTACT_3, Contact::class));
        $manager->persist($family);
        $this->addReference(self::BOOK_FAMILY, $family);

        $work = new Book();
        $work->setUuid(Uuid::v4()->toRfc4122());
        $work->setName('Travail');
        $work->addContact($this->getReference(ContactFixture::CONTACT_3, Contact::class));
        $work->addContact($this->getReference(ContactFixture::CONTACT_4, Contact::class));
        $manager->persist($work);
        $this->addReference(self::BOOK_WORK, $work);

        $manager->flush();
    }

    public function getDependencies(): array
    {
        return [ContactFixture::class];
    }
}
