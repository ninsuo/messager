<?php

namespace App\Tests\Security;

use App\Repository\FakeSmsRepository;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class FirstFactorVerifyAuthenticatorTest extends WebTestCase
{
    public function testValidCodeAuthenticates(): void
    {
        $client = static::createClient();

        $secret = $this->submitPhoneAndGetSecret($client, '+33600000002');
        $code = $this->getCodeFromSms($client, '+33600000002');

        $crawler = $client->request('GET', '/verify/'.$secret);
        $form = $crawler->filter('form')->form();
        $form['verify_code_form[code]'] = $code;
        $client->submit($form);

        $this->assertResponseRedirects('/');
        $client->followRedirect();
        $this->assertSelectorTextContains('h1', 'Déclenchements');
    }

    public function testValidCodeWithSpacesAuthenticates(): void
    {
        $client = static::createClient();

        $secret = $this->submitPhoneAndGetSecret($client, '+33600000002');
        $code = $this->getCodeFromSms($client, '+33600000002');

        $codeWithSpace = substr($code, 0, 3).' '.substr($code, 3);

        $crawler = $client->request('GET', '/verify/'.$secret);
        $form = $crawler->filter('form')->form();
        $form['verify_code_form[code]'] = $codeWithSpace;
        $client->submit($form);

        $this->assertResponseRedirects('/');
        $client->followRedirect();
        $this->assertSelectorTextContains('h1', 'Déclenchements');
    }

    public function testInvalidCodeFails(): void
    {
        $client = static::createClient();

        $secret = $this->submitPhoneAndGetSecret($client, '+33600000002');

        $crawler = $client->request('GET', '/verify/'.$secret);
        $form = $crawler->filter('form')->form();
        $form['verify_code_form[code]'] = '000000';
        $client->submit($form);

        $this->assertResponseRedirects('/verify/'.$secret);
        $client->followRedirect();
        $this->assertSelectorExists('.alert-danger');
    }

    public function testExpiredCodeFails(): void
    {
        $client = static::createClient();

        $secret = $this->submitPhoneAndGetSecret($client, '+33600000002');

        // Invalidate the code
        $codeManager = self::getContainer()->get(\App\Manager\UnguessableCodeManager::class);
        $codeManager->invalidate($secret);

        $crawler = $client->request('GET', '/verify/'.$secret);
        $form = $crawler->filter('form')->form();
        $form['verify_code_form[code]'] = '123456';
        $client->submit($form);

        $this->assertResponseRedirects('/verify/'.$secret);
        $client->followRedirect();
        $this->assertSelectorExists('.alert-danger');
    }

    public function testUnknownPhoneShowsGenericError(): void
    {
        $client = static::createClient();

        $secret = $this->submitPhoneAndGetSecret($client, '+33699999999');

        // We can't get a real code from SMS (none was sent), so use any code.
        // The unguessable code still holds the verification code in its context.
        $codeManager = self::getContainer()->get(\App\Manager\UnguessableCodeManager::class);
        $context = $codeManager->get('auth', $secret, onlyValidate: true);
        $code = $context['code'];

        $crawler = $client->request('GET', '/verify/'.$secret);
        $form = $crawler->filter('form')->form();
        $form['verify_code_form[code]'] = $code;
        $client->submit($form);

        // Should fail with generic "incorrect code" message, not reveal user doesn't exist
        $this->assertResponseRedirects('/verify/'.$secret);
        $client->followRedirect();
        $this->assertSelectorExists('.alert-danger');
    }

    public function testRememberMeChecked(): void
    {
        $client = static::createClient();

        $secret = $this->submitPhoneAndGetSecret($client, '+33600000002');
        $code = $this->getCodeFromSms($client, '+33600000002');

        $crawler = $client->request('GET', '/verify/'.$secret);
        $form = $crawler->filter('form')->form();
        $form['verify_code_form[code]'] = $code;
        $form['_remember_me'] = '1';
        $client->submit($form);

        $this->assertResponseRedirects('/');

        $cookies = $client->getCookieJar();
        $rememberMe = $cookies->get('REMEMBERME');
        $this->assertNotNull($rememberMe);
    }

    public function testRememberMeUnchecked(): void
    {
        $client = static::createClient();

        $secret = $this->submitPhoneAndGetSecret($client, '+33600000002');
        $code = $this->getCodeFromSms($client, '+33600000002');

        $crawler = $client->request('GET', '/verify/'.$secret);
        $form = $crawler->filter('form')->form();
        $form['verify_code_form[code]'] = $code;
        // Don't check _remember_me
        $client->submit($form);

        $this->assertResponseRedirects('/');

        $cookies = $client->getCookieJar();
        $rememberMe = $cookies->get('REMEMBERME');
        $this->assertNull($rememberMe);
    }

    public function testLogout(): void
    {
        $client = static::createClient();

        // Authenticate first
        $secret = $this->submitPhoneAndGetSecret($client, '+33600000002');
        $code = $this->getCodeFromSms($client, '+33600000002');

        $crawler = $client->request('GET', '/verify/'.$secret);
        $form = $crawler->filter('form')->form();
        $form['verify_code_form[code]'] = $code;
        $client->submit($form);
        $client->followRedirect();
        $this->assertSelectorTextContains('h1', 'Déclenchements');

        // Now logout
        $client->request('GET', '/logout');
        $this->assertResponseRedirects();
        $client->followRedirect();

        // Should see login form again
        $this->assertSelectorTextContains('h1', 'Connexion');
    }

    private function submitPhoneAndGetSecret(
        \Symfony\Bundle\FrameworkBundle\KernelBrowser $client,
        string $phone,
    ): string {
        $crawler = $client->request('GET', '/');
        $form = $crawler->filter('form')->form();
        $form['phone_form[phone]'] = $phone;
        $client->submit($form);

        $location = $client->getResponse()->headers->get('Location');
        $this->assertNotNull($location);

        // Extract the secret from /verify/{secret}
        preg_match('#/verify/([a-f0-9]+)#', $location, $matches);
        $this->assertArrayHasKey(1, $matches);

        return $matches[1];
    }

    private function getCodeFromSms(
        \Symfony\Bundle\FrameworkBundle\KernelBrowser $client,
        string $phone,
    ): string {
        $fakeSmsRepository = self::getContainer()->get(FakeSmsRepository::class);
        $sms = $fakeSmsRepository->findOneBy(['toNumber' => $phone]);

        $this->assertNotNull($sms);

        preg_match('/(\d{3}) (\d{3})/', $sms->getMessage(), $matches);
        $this->assertArrayHasKey(1, $matches);
        $this->assertArrayHasKey(2, $matches);

        return $matches[1].$matches[2];
    }
}
