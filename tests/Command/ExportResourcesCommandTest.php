<?php

namespace App\Tests\Command;

use App\Repository\BookRepository;
use App\Tests\Trait\EntityFactoryTrait;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

class ExportResourcesCommandTest extends KernelTestCase
{
    use EntityFactoryTrait;

    private CommandTester $commandTester;

    protected function setUp(): void
    {
        self::bootKernel();

        $application = new Application(self::$kernel);
        $command = $application->find('resources:export');
        $this->commandTester = new CommandTester($command);
    }

    public function testExportReturnsSuccess(): void
    {
        $this->commandTester->execute([]);

        $this->assertSame(Command::SUCCESS, $this->commandTester->getStatusCode());
    }

    public function testExportOutputIsValidJson(): void
    {
        $this->commandTester->execute([]);

        $data = json_decode($this->commandTester->getDisplay(), true);
        $this->assertIsArray($data);
        $this->assertArrayHasKey('users', $data);
        $this->assertArrayHasKey('contacts', $data);
        $this->assertArrayHasKey('books', $data);
    }

    public function testExportContainsCreatedUser(): void
    {
        $this->createUser('+33699000001', false);
        $this->createUser('+33699000002', true);

        $this->commandTester->execute([]);

        $data = json_decode($this->commandTester->getDisplay(), true);
        $phones = array_column($data['users'], 'phoneNumber');
        $this->assertContains('+33699000001', $phones);
        $this->assertContains('+33699000002', $phones);

        $admins = array_column($data['users'], 'isAdmin', 'phoneNumber');
        $this->assertFalse($admins['+33699000001']);
        $this->assertTrue($admins['+33699000002']);
    }

    public function testExportContainsCreatedContacts(): void
    {
        $this->createContact('+33699100001');
        $this->createContact('+33699100002');

        $this->commandTester->execute([]);

        $data = json_decode($this->commandTester->getDisplay(), true);
        $phones = array_column($data['contacts'], 'phoneNumber');
        $this->assertContains('+33699100001', $phones);
        $this->assertContains('+33699100002', $phones);
    }

    public function testExportContainsBookWithContacts(): void
    {
        $contact1 = $this->createContact('+33699200001');
        $contact2 = $this->createContact('+33699200002');

        $book = $this->createBook('Export Test Book');
        $book->addContact($contact1);
        $book->addContact($contact2);
        self::getContainer()->get(BookRepository::class)->save($book);

        $this->commandTester->execute([]);

        $data = json_decode($this->commandTester->getDisplay(), true);

        $exportedBook = null;
        foreach ($data['books'] as $b) {
            if ('Export Test Book' === $b['name']) {
                $exportedBook = $b;
                break;
            }
        }

        $this->assertNotNull($exportedBook, 'Book "Export Test Book" should be in export');
        $this->assertCount(2, $exportedBook['contacts']);
        $this->assertContains('+33699200001', $exportedBook['contacts']);
        $this->assertContains('+33699200002', $exportedBook['contacts']);
    }

    public function testExportUserStructure(): void
    {
        $this->createUser('+33699300001');

        $this->commandTester->execute([]);

        $data = json_decode($this->commandTester->getDisplay(), true);

        $found = null;
        foreach ($data['users'] as $u) {
            if ('+33699300001' === $u['phoneNumber']) {
                $found = $u;
                break;
            }
        }

        $this->assertNotNull($found);
        $this->assertArrayHasKey('phoneNumber', $found);
        $this->assertArrayHasKey('isAdmin', $found);
        $this->assertCount(2, $found, 'User export should only contain phoneNumber and isAdmin');
    }
}
