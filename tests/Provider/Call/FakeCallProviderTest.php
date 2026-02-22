<?php

namespace App\Tests\Provider\Call;

use App\Provider\Call\CallProvider;
use App\Provider\Call\FakeCallProvider;
use App\Repository\Fake\FakeCallRepository;
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

        $this->assertGreaterThanOrEqual(2, count($all));
    }

    public function testFixtureContent(): void
    {
        $call = $this->repository->findOneBy([
            'toNumber' => '+33698765432',
        ]);

        $this->assertNotNull($call);
        $this->assertSame('+33612345678', $call->getFromNumber());
        $this->assertSame('<Response><Say>Bonjour</Say></Response>', $call->getContent());
    }

    public function testFixtureNullContent(): void
    {
        $call = $this->repository->findOneBy([
            'toNumber' => '+33611223344',
        ]);

        $this->assertNotNull($call);
        $this->assertNull($call->getContent());
    }

    public function testSendReturnsSid(): void
    {
        $sid = $this->provider->send('+33611111111');

        $this->assertNotNull($sid);
        $this->assertStringStartsWith('FAKE-', $sid);
    }

    public function testSendPersistsEntity(): void
    {
        $countBefore = count($this->repository->findAll());

        $this->provider->send('+33611111111');

        $countAfter = count($this->repository->findAll());
        $this->assertSame($countBefore + 1, $countAfter);
    }

    public function testSendStoresCorrectData(): void
    {
        $this->provider->send('+33611111111', [], 'Test content');

        $call = $this->repository->findOneBy([
            'toNumber' => '+33611111111',
        ]);

        $this->assertNotNull($call);
        $this->assertNotNull($call->getId());
        $this->assertInstanceOf(\DateTime::class, $call->getCreatedAt());
        $this->assertNotNull($call->getContent());
        $this->assertStringContainsString('Test content', $call->getContent());
    }

    public function testSendWithAuthCodeBuildsCorrectTwiml(): void
    {
        $this->provider->send('+33622222222', ['auth_code' => '123 456']);

        $call = $this->repository->findOneBy([
            'toNumber' => '+33622222222',
        ]);

        $this->assertNotNull($call);
        $this->assertStringContainsString('1, 2, 3, 4, 5, 6', $call->getContent());
        $this->assertStringContainsString('Votre code Messager est', $call->getContent());
    }

    public function testSendMultiple(): void
    {
        $this->provider->send('+33611111111');
        $this->provider->send('+33633333333');

        $calls = $this->repository->findBy(['toNumber' => '+33633333333']);

        $this->assertCount(1, $calls);
    }
}
