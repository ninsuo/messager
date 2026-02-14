<?php

namespace App\Tests\Command\Twilio;

use App\Entity\Twilio\TwilioCall;
use App\Entity\Twilio\TwilioMessage;
use App\Entity\Twilio\TwilioStatus;
use App\Repository\Twilio\TwilioCallRepository;
use App\Repository\Twilio\TwilioMessageRepository;
use App\Repository\Twilio\TwilioStatusRepository;
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
        $oldCall = $this->createCall(new \DateTime('-31 days'));
        $recentCall = $this->createCall(new \DateTime('-5 days'));

        $oldMessage = $this->createMessage(new \DateTime('-31 days'));
        $recentMessage = $this->createMessage(new \DateTime('-5 days'));

        $oldStatus = $this->createStatus(new \DateTime('-31 days'));
        $recentStatus = $this->createStatus(new \DateTime('-5 days'));

        $this->tester->execute([]);

        $this->assertSame(0, $this->tester->getStatusCode());
        $this->assertStringContainsString('1 call(s)', $this->tester->getDisplay());
        $this->assertStringContainsString('1 message(s)', $this->tester->getDisplay());
        $this->assertStringContainsString('1 status(es)', $this->tester->getDisplay());

        $callRepo = self::getContainer()->get(TwilioCallRepository::class);
        $this->assertNull($callRepo->findOneBy(['uuid' => $oldCall->getUuid()]));
        $this->assertNotNull($callRepo->findOneBy(['uuid' => $recentCall->getUuid()]));

        $messageRepo = self::getContainer()->get(TwilioMessageRepository::class);
        $this->assertNull($messageRepo->findOneBy(['uuid' => $oldMessage->getUuid()]));
        $this->assertNotNull($messageRepo->findOneBy(['uuid' => $recentMessage->getUuid()]));

        $statusRepo = self::getContainer()->get(TwilioStatusRepository::class);
        $this->assertNull($statusRepo->findOneBy(['sid' => $oldStatus->getSid()]));
        $this->assertNotNull($statusRepo->findOneBy(['sid' => $recentStatus->getSid()]));
    }

    public function testDeletesNothingWhenEmpty(): void
    {
        $this->tester->execute([]);

        $this->assertSame(0, $this->tester->getStatusCode());
        $this->assertStringContainsString('0 call(s)', $this->tester->getDisplay());
        $this->assertStringContainsString('0 message(s)', $this->tester->getDisplay());
        $this->assertStringContainsString('0 status(es)', $this->tester->getDisplay());
    }

    private function createCall(\DateTime $createdAt): TwilioCall
    {
        $call = new TwilioCall();
        $call->setUuid(Uuid::v4()->toRfc4122());
        $call->setDirection(TwilioCall::DIRECTION_OUTBOUND);
        $call->setFromNumber('+33700000000');
        $call->setToNumber('+33600000000');

        $this->em->persist($call);
        $this->em->flush();

        // Override createdAt after PrePersist callback
        $call->setCreatedAt($createdAt);
        $this->em->flush();

        return $call;
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

    private function createStatus(\DateTime $receivedAt): TwilioStatus
    {
        $status = new TwilioStatus();
        $status->setSid('SM' . Uuid::v4()->toRfc4122());
        $status->setStatus('delivered');
        $status->setReceivedAt($receivedAt);

        $this->em->persist($status);
        $this->em->flush();

        return $status;
    }
}
