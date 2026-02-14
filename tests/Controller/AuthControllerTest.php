<?php

namespace App\Tests\Controller;

use App\Repository\Fake\FakeCallRepository;
use App\Repository\Fake\FakeSmsRepository;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class AuthControllerTest extends WebTestCase
{
    public function testVerifyPageShowsVoiceCallLink(): void
    {
        $client = static::createClient();

        $secret = $this->submitPhoneAndGetSecret($client, '+33600000002');
        $client->followRedirect();

        $this->assertResponseIsSuccessful();
        $this->assertSelectorExists('form[action$="/call"] button');
        $this->assertSelectorTextContains('form[action$="/call"] button', 'appel vocal');
    }

    public function testVoiceCallTriggersCallAndRedirects(): void
    {
        $client = static::createClient();

        $secret = $this->submitPhoneAndGetSecret($client, '+33600000002');

        $client->request('POST', '/verify/'.$secret.'/call');

        $this->assertResponseRedirects('/verify/'.$secret);
        $client->followRedirect();
        $this->assertSelectorExists('.alert-success');
        $this->assertSelectorTextContains('.alert-success', 'appel vocal');
    }

    public function testVoiceCallShowsTranscriptInTestEnv(): void
    {
        $client = static::createClient();

        $secret = $this->submitPhoneAndGetSecret($client, '+33600000002');

        $client->request('POST', '/verify/'.$secret.'/call');

        $this->assertResponseRedirects('/verify/'.$secret);
        $client->followRedirect();
    }

    public function testVoiceCallCreatesFakeCall(): void
    {
        $client = static::createClient();

        $secret = $this->submitPhoneAndGetSecret($client, '+33600000002');

        $client->request('POST', '/verify/'.$secret.'/call');

        $fakeCallRepository = self::getContainer()->get(FakeCallRepository::class);
        $calls = $fakeCallRepository->findBy(['toNumber' => '+33600000002']);

        $this->assertCount(1, $calls);
        $this->assertNotNull($calls[0]->getContent());
        $this->assertStringContainsString('Votre code Messager est', $calls[0]->getContent());
    }

    public function testVoiceCallFakeCallContainsCorrectCode(): void
    {
        $client = static::createClient();

        $secret = $this->submitPhoneAndGetSecret($client, '+33600000002');
        $code = $this->getCodeFromSms($client, '+33600000002');

        $client->request('POST', '/verify/'.$secret.'/call');

        $fakeCallRepository = self::getContainer()->get(FakeCallRepository::class);
        $calls = $fakeCallRepository->findBy(['toNumber' => '+33600000002']);

        $digits = implode(', ', str_split($code));
        $this->assertStringContainsString($digits, $calls[0]->getContent());
    }

    public function testVoiceCallCooldownBlocksSecondCall(): void
    {
        $client = static::createClient();

        $secret = $this->submitPhoneAndGetSecret($client, '+33600000002');

        $client->request('POST', '/verify/'.$secret.'/call');
        $this->assertResponseRedirects('/verify/'.$secret);

        // Second call within 60s
        $client->request('POST', '/verify/'.$secret.'/call');
        $this->assertResponseRedirects('/verify/'.$secret);
        $client->followRedirect();
        $this->assertSelectorExists('.alert-danger');
        $this->assertSelectorTextContains('.alert-danger', 'patienter');
    }

    public function testVoiceCallWithInvalidSecretRedirectsHome(): void
    {
        $client = static::createClient();

        $client->request('POST', '/verify/deadbeef1234567890abcdef/call');

        $this->assertResponseRedirects('/');
        $client->followRedirect();
        $this->assertSelectorExists('.alert-danger');
    }

    public function testVoiceCallWithExpiredSecretRedirectsHome(): void
    {
        $client = static::createClient();

        $secret = $this->submitPhoneAndGetSecret($client, '+33600000002');

        // Invalidate the code
        $codeManager = self::getContainer()->get(\App\Manager\UnguessableCodeManager::class);
        $codeManager->invalidate($secret);

        $client->request('POST', '/verify/'.$secret.'/call');

        $this->assertResponseRedirects('/');
        $client->followRedirect();
        $this->assertSelectorExists('.alert-danger');
    }

    public function testVoiceCallDoesNotConsumeVerificationAttempts(): void
    {
        $client = static::createClient();

        $secret = $this->submitPhoneAndGetSecret($client, '+33600000002');
        $code = $this->getCodeFromSms($client, '+33600000002');

        // Trigger a voice call (should not consume an attempt)
        $client->request('POST', '/verify/'.$secret.'/call');

        // Now verify with the correct code — should still work
        $crawler = $client->request('GET', '/verify/'.$secret);
        $form = $crawler->filter('form')->eq(0)->form();
        $form['verify_code_form[code]'] = $code;
        $client->submit($form);

        $this->assertResponseRedirects('/');
        $client->followRedirect();
        $this->assertSelectorTextContains('h1', 'Déclenchements');
    }

    private function submitPhoneAndGetSecret(KernelBrowser $client, string $phone): string
    {
        $crawler = $client->request('GET', '/');
        $form = $crawler->filter('form')->form();
        $form['phone_form[phone]'] = $phone;
        $client->submit($form);

        $location = $client->getResponse()->headers->get('Location');
        $this->assertNotNull($location);

        preg_match('#/verify/([a-f0-9]+)#', $location, $matches);
        $this->assertArrayHasKey(1, $matches);

        return $matches[1];
    }

    private function getCodeFromSms(KernelBrowser $client, string $phone): string
    {
        $fakeSmsRepository = self::getContainer()->get(FakeSmsRepository::class);
        $sms = $fakeSmsRepository->findOneBy(['toNumber' => $phone]);

        $this->assertNotNull($sms);

        preg_match('/(\d{3}) (\d{3})/', $sms->getMessage(), $matches);
        $this->assertArrayHasKey(1, $matches);
        $this->assertArrayHasKey(2, $matches);

        return $matches[1].$matches[2];
    }
}
