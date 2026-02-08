<?php

namespace App\Tests\Provider\Call;

use App\Entity\FakeCall;
use App\Provider\Call\CallProvider;
use App\Provider\Call\FakeCallProvider;
use App\Repository\FakeCallRepository;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class FakeCallProviderTest extends KernelTestCase
{
    private FakeCallProvider $provider;
    private FakeCallRepository $repository;

    protected function setUp(): void
    {
        self::bootKernel();

        $provider = self::getContainer()->get(CallProvider::class);
        \assert($provider instanceof FakeCallProvider);
        $this->provider = $provider;
        $this->repository = self::getContainer()->get(FakeCallRepository::class);
    }

    public function testFixturesLoaded(): void
    {
        $all = $this->repository->findAll();

        $this->assertCount(3, $all);
    }

    public function testFixtureEstablishType(): void
    {
        $established = $this->repository->findBy(['type' => FakeCall::TYPE_ESTABLISH]);

        $this->assertCount(2, $established);
    }

    public function testFixtureKeyPressType(): void
    {
        $keyPresses = $this->repository->findBy(['type' => FakeCall::TYPE_KEY_PRESS]);

        $this->assertCount(1, $keyPresses);
    }

    public function testFixtureContent(): void
    {
        $call = $this->repository->findOneBy([
            'toNumber' => '+33698765432',
            'type' => FakeCall::TYPE_ESTABLISH,
        ]);

        $this->assertNotNull($call);
        $this->assertSame('+33612345678', $call->getFromNumber());
        $this->assertSame('<Response><Say>Bonjour</Say></Response>', $call->getContent());
    }

    public function testFixtureNullContent(): void
    {
        $call = $this->repository->findOneBy([
            'toNumber' => '+33611223344',
            'type' => FakeCall::TYPE_ESTABLISH,
        ]);

        $this->assertNotNull($call);
        $this->assertNull($call->getContent());
    }

    public function testSendReturnsSid(): void
    {
        $sid = $this->provider->send('+33600000000', '+33611111111');

        $this->assertNotNull($sid);
        $this->assertStringStartsWith('FAKE-', $sid);
    }

    public function testSendPersistsEntity(): void
    {
        $countBefore = count($this->repository->findAll());

        $this->provider->send('+33600000000', '+33611111111');

        $countAfter = count($this->repository->findAll());
        $this->assertSame($countBefore + 1, $countAfter);
    }

    public function testSendStoresCorrectData(): void
    {
        $this->provider->send('+33600000000', '+33611111111');

        $call = $this->repository->findOneBy([
            'fromNumber' => '+33600000000',
            'toNumber' => '+33611111111',
        ]);

        $this->assertNotNull($call);
        $this->assertSame(FakeCall::TYPE_ESTABLISH, $call->getType());
        $this->assertNotNull($call->getId());
        $this->assertInstanceOf(\DateTime::class, $call->getCreatedAt());
    }

    public function testTriggerHookStoresCorrectType(): void
    {
        $fakeCall = $this->provider->triggerHook(
            '+33600000000',
            '+33611111111',
            [],
            'test.event',
            FakeCall::TYPE_KEY_PRESS,
            '5',
        );

        $this->assertSame(FakeCall::TYPE_KEY_PRESS, $fakeCall->getType());
        $this->assertSame('+33600000000', $fakeCall->getFromNumber());
        $this->assertSame('+33611111111', $fakeCall->getToNumber());
        $this->assertNotNull($fakeCall->getId());
    }

    public function testSendMultiple(): void
    {
        $this->provider->send('+33600000000', '+33611111111');
        $this->provider->send('+33600000000', '+33622222222');

        $fromSender = $this->repository->findBy(['fromNumber' => '+33600000000']);

        $this->assertCount(2, $fromSender);
    }
}
