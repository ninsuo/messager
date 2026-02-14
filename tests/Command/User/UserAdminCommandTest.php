<?php

namespace App\Tests\Command\User;

use App\Entity\User;
use App\Repository\UserRepository;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Uid\Uuid;

class UserAdminCommandTest extends KernelTestCase
{
    private CommandTester $commandTester;
    private UserRepository $userRepository;

    protected function setUp(): void
    {
        self::bootKernel();

        $application = new Application(self::$kernel);
        $command = $application->find('user:admin');
        $this->commandTester = new CommandTester($command);
        $this->userRepository = self::getContainer()->get(UserRepository::class);
    }

    public function testGrantAdmin(): void
    {
        $this->createUser('+33612345678', false);

        $this->commandTester->execute(['phone' => '+33612345678']);

        $this->assertSame(Command::SUCCESS, $this->commandTester->getStatusCode());

        $user = $this->userRepository->findByPhoneNumber('+33612345678');
        $this->assertNotNull($user);
        $this->assertTrue($user->isAdmin());
    }

    public function testRevokeAdmin(): void
    {
        $this->createUser('+33612345678', true);

        $this->commandTester->execute(['phone' => '+33612345678', '--revoke' => true]);

        $this->assertSame(Command::SUCCESS, $this->commandTester->getStatusCode());

        $user = $this->userRepository->findByPhoneNumber('+33612345678');
        $this->assertNotNull($user);
        $this->assertFalse($user->isAdmin());
    }

    public function testUnknownPhoneFails(): void
    {
        $this->commandTester->execute(['phone' => '+33699999999']);

        $this->assertSame(Command::FAILURE, $this->commandTester->getStatusCode());
        $this->assertStringContainsString('No user found', $this->commandTester->getDisplay());
    }

    public function testAlreadyAdminIsIdempotent(): void
    {
        $this->createUser('+33612345678', true);

        $this->commandTester->execute(['phone' => '+33612345678']);

        $this->assertSame(Command::SUCCESS, $this->commandTester->getStatusCode());

        $user = $this->userRepository->findByPhoneNumber('+33612345678');
        $this->assertNotNull($user);
        $this->assertTrue($user->isAdmin());
    }

    private function createUser(string $phone, bool $isAdmin): User
    {
        $user = new User();
        $user->setUuid(Uuid::v4()->toRfc4122());
        $user->setPhoneNumber($phone);
        $user->setIsAdmin($isAdmin);

        $this->userRepository->save($user);

        return $user;
    }
}
