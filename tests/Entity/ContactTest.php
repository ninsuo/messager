<?php

namespace App\Tests\Entity;

use App\Entity\Contact;
use App\Repository\ContactRepository;
use App\Tests\Trait\EntityFactoryTrait;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Uid\Uuid;

class ContactTest extends KernelTestCase
{
    use EntityFactoryTrait;

    public function testCreateContact(): void
    {
        self::bootKernel();

        $contact = $this->createContact('+33612345678');

        $this->assertNotNull($contact->getId());
        $this->assertNotEmpty($contact->getUuid());
        $this->assertSame('+33612345678', $contact->getPhoneNumber());
        $this->assertNotNull($contact->getCreatedAt());
        $this->assertNotNull($contact->getUpdatedAt());
    }

    public function testFindByPhoneNumber(): void
    {
        self::bootKernel();

        $this->createContact('+33612345678');

        $repo = self::getContainer()->get(ContactRepository::class);
        $found = $repo->findByPhoneNumber('+33612345678');

        $this->assertNotNull($found);
        $this->assertSame('+33612345678', $found->getPhoneNumber());
    }

    public function testFindByPhoneNumberNotFound(): void
    {
        self::bootKernel();

        $repo = self::getContainer()->get(ContactRepository::class);
        $found = $repo->findByPhoneNumber('+33699999999');

        $this->assertNull($found);
    }

    public function testPhoneNumberIsEncryptedInDatabase(): void
    {
        self::bootKernel();

        $contact = $this->createContact('+33612345678');

        $em = self::getContainer()->get(EntityManagerInterface::class);
        $raw = $em->getConnection()->executeQuery(
            'SELECT phone_number FROM contact WHERE id = ?',
            [$contact->getId()]
        )->fetchOne();

        $this->assertNotSame('+33612345678', $raw);
    }

    public function testBlindPhoneNumberIsPopulated(): void
    {
        self::bootKernel();

        $contact = $this->createContact('+33612345678');

        $this->assertNotNull($contact->getBlindPhoneNumber());
        $this->assertNotEmpty($contact->getBlindPhoneNumber());
    }

    public function testRemoveContact(): void
    {
        self::bootKernel();

        $contact = $this->createContact('+33612345678');
        $id = $contact->getId();

        $repo = self::getContainer()->get(ContactRepository::class);
        $repo->remove($contact);

        $this->assertNull($repo->find($id));
    }

    public function testFluentSetters(): void
    {
        $contact = new Contact();

        $result = $contact->setUuid('test-uuid');
        $this->assertSame($contact, $result);

        $result = $contact->setPhoneNumber('+33600000000');
        $this->assertSame($contact, $result);
    }
}
