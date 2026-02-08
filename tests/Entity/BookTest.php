<?php

namespace App\Tests\Entity;

use App\Entity\Book;
use App\Repository\BookRepository;
use App\Tests\Trait\EntityFactoryTrait;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class BookTest extends KernelTestCase
{
    use EntityFactoryTrait;

    public function testCreateBook(): void
    {
        self::bootKernel();

        $book = $this->createBook('Drivers');

        $this->assertNotNull($book->getId());
        $this->assertNotEmpty($book->getUuid());
        $this->assertSame('Drivers', $book->getName());
        $this->assertNotNull($book->getCreatedAt());
        $this->assertNotNull($book->getUpdatedAt());
    }

    public function testAddContact(): void
    {
        self::bootKernel();

        $book = $this->createBook('Drivers');
        $contact = $this->createContact('+33611111111');

        $book->addContact($contact);

        $repo = self::getContainer()->get(BookRepository::class);
        $repo->save($book);

        $this->assertCount(1, $book->getContacts());
        $this->assertSame($contact, $book->getContacts()->first());
    }

    public function testAddContactIsIdempotent(): void
    {
        self::bootKernel();

        $book = $this->createBook('Drivers');
        $contact = $this->createContact('+33611111111');

        $book->addContact($contact);
        $book->addContact($contact);

        $this->assertCount(1, $book->getContacts());
    }

    public function testRemoveContact(): void
    {
        self::bootKernel();

        $book = $this->createBook('Drivers');
        $contact = $this->createContact('+33611111111');

        $book->addContact($contact);
        $this->assertCount(1, $book->getContacts());

        $book->removeContact($contact);
        $this->assertCount(0, $book->getContacts());
    }

    public function testMultipleContacts(): void
    {
        self::bootKernel();

        $book = $this->createBook('Drivers');
        $contact1 = $this->createContact('+33611111111');
        $contact2 = $this->createContact('+33622222222');

        $book->addContact($contact1);
        $book->addContact($contact2);

        $repo = self::getContainer()->get(BookRepository::class);
        $repo->save($book);

        $this->assertCount(2, $book->getContacts());
    }

    public function testRemoveBook(): void
    {
        self::bootKernel();

        $book = $this->createBook('Drivers');
        $id = $book->getId();

        $repo = self::getContainer()->get(BookRepository::class);
        $repo->remove($book);

        $this->assertNull($repo->find($id));
    }

    public function testFluentSetters(): void
    {
        $book = new Book();

        $result = $book->setUuid('test-uuid');
        $this->assertSame($book, $result);

        $result = $book->setName('Test');
        $this->assertSame($book, $result);
    }
}
