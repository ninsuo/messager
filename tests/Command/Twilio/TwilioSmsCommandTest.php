<?php

namespace App\Tests\Command\Twilio;

use App\Repository\Fake\FakeSmsRepository;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

class TwilioSmsCommandTest extends KernelTestCase
{
    private CommandTester $commandTester;
    private FakeSmsRepository $fakeSmsRepository;

    protected function setUp(): void
    {
        self::bootKernel();

        $application = new Application(self::$kernel);
        $command = $application->find('twilio:sms');
        $this->commandTester = new CommandTester($command);
        $this->fakeSmsRepository = self::getContainer()->get(FakeSmsRepository::class);
    }

    public function testSendSms(): void
    {
        $this->commandTester->execute(['to' => '+33612345678', 'message' => 'Hello from CLI']);

        $this->assertSame(Command::SUCCESS, $this->commandTester->getStatusCode());
        $this->assertStringContainsString('SMS sent', $this->commandTester->getDisplay());
    }

    public function testSendSmsPersistsEntity(): void
    {
        $countBefore = count($this->fakeSmsRepository->findAll());

        $this->commandTester->execute(['to' => '+33612345678', 'message' => 'Test message']);

        $countAfter = count($this->fakeSmsRepository->findAll());
        $this->assertSame($countBefore + 1, $countAfter);
    }

    public function testSendSmsStoresCorrectData(): void
    {
        $this->commandTester->execute(['to' => '+33699887766', 'message' => 'CLI test content']);

        $sms = $this->fakeSmsRepository->findOneBy(['toNumber' => '+33699887766']);

        $this->assertNotNull($sms);
        $this->assertSame('CLI test content', $sms->getMessage());
    }

    public function testInvalidPhoneNumberFails(): void
    {
        $this->commandTester->execute(['to' => 'invalid', 'message' => 'Hello']);

        $this->assertSame(Command::FAILURE, $this->commandTester->getStatusCode());
        $this->assertStringContainsString('Invalid French phone number', $this->commandTester->getDisplay());
    }
}
