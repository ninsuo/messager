<?php

namespace App\Tests\Command\Twilio;

use App\Entity\Twilio\TwilioMessage;
use App\Repository\Twilio\TwilioMessageRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Uid\Uuid;

class TwilioCleanCommandTest extends KernelTestCase
{
    private CommandTester $tester;
    private EntityManagerInterface $em;

    protected function setUp(): void
    {
        self::bootKernel();

        $application = new Application(self::$kernel);
        $command = $application->find('twilio:clean');
        $this->tester = new CommandTester($command);
        $this->em = self::getContainer()->get(EntityManagerInterface::class);
    }

    public function testDeletesOldEntitiesAndKeepsRecent(): void
    {
        $oldMessage = $this->createMessage(new \DateTime('-31 days'));
        $recentMessage = $this->createMessage(new \DateTime('-5 days'));

        $this->tester->execute([]);

        $this->assertSame(0, $this->tester->getStatusCode());
        $this->assertStringContainsString('1 message(s)', $this->tester->getDisplay());

        $messageRepo = self::getContainer()->get(TwilioMessageRepository::class);
        $this->assertNull($messageRepo->findOneBy(['uuid' => $oldMessage->getUuid()]));
        $this->assertNotNull($messageRepo->findOneBy(['uuid' => $recentMessage->getUuid()]));
    }

    public function testDeletesNothingWhenEmpty(): void
    {
        $this->tester->execute([]);

        $this->assertSame(0, $this->tester->getStatusCode());
        $this->assertStringContainsString('0 message(s)', $this->tester->getDisplay());
    }

    private function createMessage(\DateTime $createdAt): TwilioMessage
    {
        $message = new TwilioMessage();
        $message->setUuid(Uuid::v4()->toRfc4122());
        $message->setDirection(TwilioMessage::DIRECTION_OUTBOUND);
        $message->setFromNumber('+33700000000');
        $message->setToNumber('+33600000000');

        $this->em->persist($message);
        $this->em->flush();

        // Override createdAt after PrePersist callback
        $message->setCreatedAt($createdAt);
        $this->em->flush();

        return $message;
    }
}
