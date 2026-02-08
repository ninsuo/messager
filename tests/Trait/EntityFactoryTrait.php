<?php

namespace App\Tests\Trait;

use App\Entity\Book;
use App\Entity\Contact;
use App\Entity\Message;
use App\Entity\Trigger;
use App\Entity\User;
use App\Repository\BookRepository;
use App\Repository\ContactRepository;
use App\Repository\MessageRepository;
use App\Repository\TriggerRepository;
use App\Repository\UserRepository;
use Symfony\Component\Uid\Uuid;

trait EntityFactoryTrait
{
    private function createUser(string $phone = '+33600000001', bool $admin = false): User
    {
        $user = new User();
        $user->setUuid(Uuid::v4()->toRfc4122());
        $user->setPhoneNumber($phone);
        $user->setIsAdmin($admin);

        self::getContainer()->get(UserRepository::class)->save($user);

        return $user;
    }

    private function createContact(string $phone = '+33611111111'): Contact
    {
        $contact = new Contact();
        $contact->setUuid(Uuid::v4()->toRfc4122());
        $contact->setPhoneNumber($phone);

        self::getContainer()->get(ContactRepository::class)->save($contact);

        return $contact;
    }

    private function createBook(string $name = 'Test Book'): Book
    {
        $book = new Book();
        $book->setUuid(Uuid::v4()->toRfc4122());
        $book->setName($name);

        self::getContainer()->get(BookRepository::class)->save($book);

        return $book;
    }

    private function createTrigger(
        ?User $user = null,
        string $type = Trigger::TYPE_SMS,
        string $content = 'Test message content',
    ): Trigger {
        $trigger = new Trigger();
        $trigger->setUuid(Uuid::v4()->toRfc4122());
        $trigger->setUser($user ?? $this->createUser());
        $trigger->setType($type);
        $trigger->setContent($content);

        self::getContainer()->get(TriggerRepository::class)->save($trigger);

        return $trigger;
    }

    private function createMessage(
        ?Trigger $trigger = null,
        ?Contact $contact = null,
    ): Message {
        $message = new Message();
        $message->setUuid(Uuid::v4()->toRfc4122());
        $message->setTrigger($trigger ?? $this->createTrigger());
        $message->setContact($contact ?? $this->createContact());

        self::getContainer()->get(MessageRepository::class)->save($message);

        return $message;
    }
}
