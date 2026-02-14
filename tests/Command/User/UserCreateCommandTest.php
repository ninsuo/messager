<?php

namespace App\Tests\Command\User;

use App\Doctrine\EventSubscriber\EncryptedResourceSubscriber;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

class UserCreateCommandTest extends KernelTestCase
{
    private CommandTester $commandTester;
    private UserRepository $userRepository;
    private EntityManagerInterface $em;

    protected function setUp(): void
    {
        self::bootKernel();

        $application = new Application(self::$kernel);
        $command = $application->find('user:create');
        $this->commandTester = new CommandTester($command);
        $this->userRepository = self::getContainer()->get(UserRepository::class);
        $this->em = self::getContainer()->get(EntityManagerInterface::class);
    }

    public function testCreateUser(): void
    {
        $this->commandTester->execute(['phone' => '+33612345678']);

        $this->assertSame(Command::SUCCESS, $this->commandTester->getStatusCode());

        $user = $this->userRepository->findByPhoneNumber('+33612345678');
        $this->assertNotNull($user);
        $this->assertFalse($user->isAdmin());
    }

    public function testCreateAdmin(): void
    {
        $this->commandTester->execute(['phone' => '+33612345678', '--admin' => true]);

        $this->assertSame(Command::SUCCESS, $this->commandTester->getStatusCode());

        $user = $this->userRepository->findByPhoneNumber('+33612345678');
        $this->assertNotNull($user);
        $this->assertTrue($user->isAdmin());
    }

    public function testCreateDuplicatePhoneFails(): void
    {
        $this->commandTester->execute(['phone' => '+33612345678']);
        $this->assertSame(Command::SUCCESS, $this->commandTester->getStatusCode());

        $this->commandTester->execute(['phone' => '+33612345678']);
        $this->assertSame(Command::FAILURE, $this->commandTester->getStatusCode());
        $this->assertStringContainsString('already exists', $this->commandTester->getDisplay());
    }

    public function testCreatedUserHasUuid(): void
    {
        $this->commandTester->execute(['phone' => '+33612345678']);

        $user = $this->userRepository->findByPhoneNumber('+33612345678');
        $this->assertNotNull($user);
        $this->assertNotEmpty($user->getUuid());
        $this->assertSame(36, strlen($user->getUuid()));
    }

    public function testCreatedUserPhoneIsEncrypted(): void
    {
        $this->commandTester->execute(['phone' => '+33612345678']);

        $user = $this->userRepository->findByPhoneNumber('+33612345678');
        $this->assertNotNull($user);

        $conn = $this->em->getConnection();
        $raw = $conn->executeQuery(
            'SELECT phone_number FROM `user` WHERE id = ?',
            [$user->getId()]
        )->fetchOne();

        $this->assertNotSame('+33612345678', $raw);
        $this->assertStringEndsWith(EncryptedResourceSubscriber::ENCRYPTION_MARKER, $raw);
    }
}
