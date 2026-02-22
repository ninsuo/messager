<?php

namespace App\Tests\Controller;

use App\Repository\Fake\FakeCallRepository;
use App\Repository\Fake\FakeSmsRepository;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class SandboxControllerTest extends WebTestCase
{
    public function testIndexPageLoads(): void
    {
        $client = static::createClient();
        $client->request('GET', '/sandbox');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('h1', 'Sandbox');
    }

    public function testSmsPageLoads(): void
    {
        $client = static::createClient();
        $client->request('GET', '/sandbox/sms');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('h1', 'SMS');
    }

    public function testSmsPageShowsData(): void
    {
        $client = static::createClient();
        $crawler = $client->request('GET', '/sandbox/sms');

        $this->assertResponseIsSuccessful();

        $fakeSmsRepository = self::getContainer()->get(FakeSmsRepository::class);
        $allSms = $fakeSmsRepository->findAll();
        $this->assertNotEmpty($allSms);

        $tableText = $crawler->filter('table')->text();
        $this->assertStringContainsString('+33612345678', $tableText);
    }

    public function testCallsPageLoads(): void
    {
        $client = static::createClient();
        $client->request('GET', '/sandbox/calls');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('h1', 'Appels');
    }

    public function testCallsPageShowsData(): void
    {
        $client = static::createClient();
        $crawler = $client->request('GET', '/sandbox/calls');

        $this->assertResponseIsSuccessful();

        $fakeCallRepository = self::getContainer()->get(FakeCallRepository::class);
        $allCalls = $fakeCallRepository->findAll();
        $this->assertNotEmpty($allCalls);

        $pageText = $crawler->text();
        $this->assertStringContainsString('+33612345678', $pageText);
        $this->assertStringContainsString('Bonjour', $pageText);
    }
}
