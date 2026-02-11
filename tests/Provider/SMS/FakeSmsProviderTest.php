<?php

namespace App\Tests\Provider\SMS;

use App\Entity\FakeSms;
use App\Provider\SMS\SmsProvider;
use App\Repository\FakeSmsRepository;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class FakeSmsProviderTest extends KernelTestCase
{
    private SmsProvider $provider;
    private FakeSmsRepository $repository;

    protected function setUp(): void
    {
        self::bootKernel();

        $this->provider = self::getContainer()->get(SmsProvider::class);
        $this->repository = self::getContainer()->get(FakeSmsRepository::class);
    }

    public function testFixturesLoaded(): void
    {
        $all = $this->repository->findAll();

        $this->assertCount(3, $all);
    }

    public function testFixtureSentDirection(): void
    {
        $sent = $this->repository->findBy(['direction' => FakeSms::DIRECTION_SENT]);

        $this->assertCount(2, $sent);
    }

    public function testFixtureReceivedDirection(): void
    {
        $received = $this->repository->findBy(['direction' => FakeSms::DIRECTION_RECEIVED]);

        $this->assertCount(1, $received);
    }

    public function testFixtureContent(): void
    {
        $sms = $this->repository->findOneBy(['toNumber' => '+33698765432', 'direction' => FakeSms::DIRECTION_SENT]);

        $this->assertNotNull($sms);
        $this->assertSame('+33612345678', $sms->getFromNumber());
        $this->assertSame('Bonjour, ceci est un test.', $sms->getMessage());
    }

    public function testSendReturnsSid(): void
    {
        $sid = $this->provider->send('+33611111111', 'Hello');

        $this->assertNotNull($sid);
        $this->assertStringStartsWith('FAKE-', $sid);
    }

    public function testSendPersistsEntity(): void
    {
        $countBefore = count($this->repository->findAll());

        $this->provider->send('+33611111111', 'Test message');

        $countAfter = count($this->repository->findAll());
        $this->assertSame($countBefore + 1, $countAfter);
    }

    public function testSendStoresCorrectData(): void
    {
        $this->provider->send('+33611111111', 'Contenu du SMS');

        $sms = $this->repository->findOneBy([
            'fromNumber' => 'Messager',
            'toNumber' => '+33611111111',
        ]);

        $this->assertNotNull($sms);
        $this->assertSame('Contenu du SMS', $sms->getMessage());
        $this->assertSame(FakeSms::DIRECTION_SENT, $sms->getDirection());
        $this->assertNotNull($sms->getId());
        $this->assertInstanceOf(\DateTime::class, $sms->getCreatedAt());
    }

    public function testSendMultiple(): void
    {
        $this->provider->send('+33611111111', 'First');
        $this->provider->send('+33622222222', 'Second');

        $fromSender = $this->repository->findBy(['fromNumber' => 'Messager']);

        $this->assertGreaterThanOrEqual(2, count($fromSender));
    }
}
