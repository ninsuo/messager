<?php

namespace App\Tests\Command\Twilio;

use App\Repository\Fake\FakeCallRepository;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

class TwilioCallCommandTest extends KernelTestCase
{
    private CommandTester $commandTester;
    private FakeCallRepository $fakeCallRepository;

    protected function setUp(): void
    {
        self::bootKernel();

        $application = new Application(self::$kernel);
        $command = $application->find('twilio:call');
        $this->commandTester = new CommandTester($command);
        $this->fakeCallRepository = self::getContainer()->get(FakeCallRepository::class);
    }

    public function testSendCall(): void
    {
        $this->commandTester->execute(['to' => '+33612345678', 'message' => 'Hello from CLI']);

        $this->assertSame(Command::SUCCESS, $this->commandTester->getStatusCode());
        $this->assertStringContainsString('Call initiated', $this->commandTester->getDisplay());
    }

    public function testSendCallPersistsEntity(): void
    {
        $countBefore = count($this->fakeCallRepository->findAll());

        $this->commandTester->execute(['to' => '+33612345678', 'message' => 'Test message']);

        $countAfter = count($this->fakeCallRepository->findAll());
        $this->assertSame($countBefore + 1, $countAfter);
    }

    public function testSendCallStoresCorrectData(): void
    {
        $this->commandTester->execute(['to' => '+33699887766', 'message' => 'CLI call content']);

        $call = $this->fakeCallRepository->findOneBy(['toNumber' => '+33699887766']);

        $this->assertNotNull($call);
        $this->assertStringContainsString('CLI call content', $call->getContent());
    }

    public function testInvalidPhoneNumberFails(): void
    {
        $this->commandTester->execute(['to' => 'invalid', 'message' => 'Hello']);

        $this->assertSame(Command::FAILURE, $this->commandTester->getStatusCode());
        $this->assertStringContainsString('Invalid French phone number', $this->commandTester->getDisplay());
    }
}
