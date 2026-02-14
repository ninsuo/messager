<?php

namespace App\Tests\Command\Resources;

use App\Repository\BookRepository;
use App\Repository\ContactRepository;
use App\Repository\UserRepository;
use App\Tests\Trait\EntityFactoryTrait;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

class ImportResourcesCommandTest extends KernelTestCase
{
    use EntityFactoryTrait;

    private CommandTester $commandTester;
    private UserRepository $userRepository;
    private ContactRepository $contactRepository;
    private BookRepository $bookRepository;

    protected function setUp(): void
    {
        self::bootKernel();

        $application = new Application(self::$kernel);
        $command = $application->find('resources:import');
        $this->commandTester = new CommandTester($command);
        $this->userRepository = self::getContainer()->get(UserRepository::class);
        $this->contactRepository = self::getContainer()->get(ContactRepository::class);
        $this->bookRepository = self::getContainer()->get(BookRepository::class);
    }

    public function testImportInvalidJson(): void
    {
        $this->commandTester->execute(['json' => 'not json']);

        $this->assertSame(Command::FAILURE, $this->commandTester->getStatusCode());
        $this->assertStringContainsString('Invalid JSON', $this->commandTester->getDisplay());
    }

    public function testImportUsers(): void
    {
        $json = json_encode([
            'users' => [
                ['phoneNumber' => '+33698000001', 'isAdmin' => false],
                ['phoneNumber' => '+33698000002', 'isAdmin' => true],
            ],
        ]);

        $this->commandTester->execute(['json' => $json]);

        $this->assertSame(Command::SUCCESS, $this->commandTester->getStatusCode());

        $user1 = $this->userRepository->findByPhoneNumber('+33698000001');
        $this->assertNotNull($user1);
        $this->assertFalse($user1->isAdmin());

        $user2 = $this->userRepository->findByPhoneNumber('+33698000002');
        $this->assertNotNull($user2);
        $this->assertTrue($user2->isAdmin());
    }

    public function testImportSkipsExistingUsers(): void
    {
        $this->createUser('+33698100001', false);

        $json = json_encode([
            'users' => [
                ['phoneNumber' => '+33698100001', 'isAdmin' => true],
                ['phoneNumber' => '+33698100002', 'isAdmin' => false],
            ],
        ]);

        $this->commandTester->execute(['json' => $json]);

        $this->assertSame(Command::SUCCESS, $this->commandTester->getStatusCode());
        $display = $this->commandTester->getDisplay();
        $this->assertStringContainsString('1 created', $display);
        $this->assertStringContainsString('1 skipped', $display);

        // Existing user should NOT be modified
        $user1 = $this->userRepository->findByPhoneNumber('+33698100001');
        $this->assertNotNull($user1);
        $this->assertFalse($user1->isAdmin());
    }

    public function testImportContacts(): void
    {
        $json = json_encode([
            'contacts' => [
                ['phoneNumber' => '+33698200001'],
                ['phoneNumber' => '+33698200002'],
            ],
        ]);

        $this->commandTester->execute(['json' => $json]);

        $this->assertSame(Command::SUCCESS, $this->commandTester->getStatusCode());

        $this->assertNotNull($this->contactRepository->findByPhoneNumber('+33698200001'));
        $this->assertNotNull($this->contactRepository->findByPhoneNumber('+33698200002'));
    }

    public function testImportSkipsExistingContacts(): void
    {
        $this->createContact('+33698300001');

        $json = json_encode([
            'contacts' => [
                ['phoneNumber' => '+33698300001'],
                ['phoneNumber' => '+33698300002'],
            ],
        ]);

        $this->commandTester->execute(['json' => $json]);

        $this->assertSame(Command::SUCCESS, $this->commandTester->getStatusCode());
        $display = $this->commandTester->getDisplay();
        $this->assertStringContainsString('1 created', $display);
        $this->assertStringContainsString('1 skipped', $display);
    }

    public function testImportBooksWithContacts(): void
    {
        $json = json_encode([
            'contacts' => [
                ['phoneNumber' => '+33698400001'],
                ['phoneNumber' => '+33698400002'],
            ],
            'books' => [
                [
                    'name' => 'Import Test Book',
                    'contacts' => ['+33698400001', '+33698400002'],
                ],
            ],
        ]);

        $this->commandTester->execute(['json' => $json]);

        $this->assertSame(Command::SUCCESS, $this->commandTester->getStatusCode());

        $books = $this->bookRepository->findBy(['name' => 'Import Test Book']);
        $this->assertCount(1, $books);

        $book = $books[0];
        $this->assertCount(2, $book->getContacts());

        $phones = $book->getContacts()->map(fn ($c) => $c->getPhoneNumber())->toArray();
        $this->assertContains('+33698400001', $phones);
        $this->assertContains('+33698400002', $phones);
    }

    public function testImportBooksLinkToExistingContacts(): void
    {
        $this->createContact('+33698500001');

        $json = json_encode([
            'books' => [
                [
                    'name' => 'Book With Existing Contact',
                    'contacts' => ['+33698500001'],
                ],
            ],
        ]);

        $this->commandTester->execute(['json' => $json]);

        $this->assertSame(Command::SUCCESS, $this->commandTester->getStatusCode());

        $books = $this->bookRepository->findBy(['name' => 'Book With Existing Contact']);
        $this->assertCount(1, $books);
        $this->assertCount(1, $books[0]->getContacts());
        $this->assertSame('+33698500001', $books[0]->getContacts()->first()->getPhoneNumber());
    }

    public function testImportEmptyData(): void
    {
        $this->commandTester->execute(['json' => '{}']);

        $this->assertSame(Command::SUCCESS, $this->commandTester->getStatusCode());
        $this->assertStringContainsString('0 created', $this->commandTester->getDisplay());
    }

    public function testFlipFlop(): void
    {
        $application = new Application(self::$kernel);
        $em = self::getContainer()->get(EntityManagerInterface::class);

        // --- Snapshot: export all existing resources ---
        $exportTester = new CommandTester($application->find('resources:export'));
        $exportTester->execute([]);
        $this->assertSame(Command::SUCCESS, $exportTester->getStatusCode());

        $exportedJson = $exportTester->getDisplay();
        $before = json_decode($exportedJson, true);
        $this->assertIsArray($before);

        $userCountBefore = count($before['users']);
        $contactCountBefore = count($before['contacts']);
        $bookCountBefore = count($before['books']);

        // Collect book→contacts mapping for later comparison
        $bookContactsBefore = [];
        foreach ($before['books'] as $b) {
            $contacts = $b['contacts'];
            sort($contacts);
            $bookContactsBefore[$b['name']] = $contacts;
        }

        // --- Flip: delete everything (respect FK order) ---
        $conn = $em->getConnection();
        $conn->executeStatement('DELETE FROM message');
        $conn->executeStatement('DELETE FROM trigger_contact');
        $conn->executeStatement('DELETE FROM `trigger`');
        $conn->executeStatement('DELETE FROM book_contact');
        $conn->executeStatement('DELETE FROM book');
        $conn->executeStatement('DELETE FROM contact');
        $conn->executeStatement('DELETE FROM `user`');

        // Clear the entity manager so it doesn't serve stale cached entities
        $em->clear();

        // Verify everything is gone
        $exportTester2 = new CommandTester($application->find('resources:export'));
        $exportTester2->execute([]);
        $empty = json_decode($exportTester2->getDisplay(), true);
        $this->assertCount(0, $empty['users'], 'All users should be deleted');
        $this->assertCount(0, $empty['contacts'], 'All contacts should be deleted');
        $this->assertCount(0, $empty['books'], 'All books should be deleted');

        // --- Flop: reimport the snapshot ---
        $this->commandTester->execute(['json' => $exportedJson]);
        $this->assertSame(Command::SUCCESS, $this->commandTester->getStatusCode());
        $this->assertStringContainsString(sprintf('%d created', $userCountBefore), $this->commandTester->getDisplay());

        // --- Verify: export again and compare ---
        $exportTester3 = new CommandTester($application->find('resources:export'));
        $exportTester3->execute([]);
        $after = json_decode($exportTester3->getDisplay(), true);

        // Same number of users, contacts, books
        $this->assertCount($userCountBefore, $after['users'], 'User count should match after reimport');
        $this->assertCount($contactCountBefore, $after['contacts'], 'Contact count should match after reimport');
        $this->assertCount($bookCountBefore, $after['books'], 'Book count should match after reimport');

        // Same user phones and admin flags
        $beforeUsersByPhone = array_column($before['users'], 'isAdmin', 'phoneNumber');
        $afterUsersByPhone = array_column($after['users'], 'isAdmin', 'phoneNumber');
        ksort($beforeUsersByPhone);
        ksort($afterUsersByPhone);
        $this->assertSame($beforeUsersByPhone, $afterUsersByPhone, 'Users should have same phones and admin flags');

        // Same contact phones
        $beforeContactPhones = array_column($before['contacts'], 'phoneNumber');
        $afterContactPhones = array_column($after['contacts'], 'phoneNumber');
        sort($beforeContactPhones);
        sort($afterContactPhones);
        $this->assertSame($beforeContactPhones, $afterContactPhones, 'Contacts should have same phones');

        // Same book names and book→contact associations
        $bookContactsAfter = [];
        foreach ($after['books'] as $b) {
            $contacts = $b['contacts'];
            sort($contacts);
            $bookContactsAfter[$b['name']] = $contacts;
        }
        ksort($bookContactsBefore);
        ksort($bookContactsAfter);
        $this->assertSame($bookContactsBefore, $bookContactsAfter, 'Books should have same contacts');
    }
}
