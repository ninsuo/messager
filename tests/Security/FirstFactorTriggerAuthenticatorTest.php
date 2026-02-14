<?php

namespace App\Tests\Security;

use App\Entity\Fake\FakeSms;
use App\Repository\Fake\FakeSmsRepository;
use App\Repository\UserRepository;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class FirstFactorTriggerAuthenticatorTest extends WebTestCase
{
    public function testPhoneSubmissionRedirectsToVerify(): void
    {
        $client = static::createClient();

        $crawler = $client->request('GET', '/');
        $form = $crawler->filter('form')->form();
        $form['phone_form[phone]'] = '+33600000002';
        $client->submit($form);

        $this->assertResponseRedirects();
        $location = $client->getResponse()->headers->get('Location');
        $this->assertNotNull($location);
        $this->assertStringContainsString('/verify/', $location);
    }

    public function testPhoneSubmissionSendsCodeSms(): void
    {
        $client = static::createClient();

        $crawler = $client->request('GET', '/');
        $form = $crawler->filter('form')->form();
        $form['phone_form[phone]'] = '+33600000002';
        $client->submit($form);

        $fakeSmsRepository = self::getContainer()->get(FakeSmsRepository::class);
        $allSms = $fakeSmsRepository->findBy(['toNumber' => '+33600000002']);

        $this->assertCount(1, $allSms);
        $this->assertSame(FakeSms::DIRECTION_SENT, $allSms[0]->getDirection());
        $this->assertMatchesRegularExpression('/\d{3} \d{3}/', $allSms[0]->getMessage());
    }

    public function testUnknownPhoneRedirectsToVerifyWithoutSms(): void
    {
        $client = static::createClient();

        $crawler = $client->request('GET', '/');
        $form = $crawler->filter('form')->form();
        $form['phone_form[phone]'] = '+33699999999';
        $client->submit($form);

        $this->assertResponseRedirects();
        $location = $client->getResponse()->headers->get('Location');
        $this->assertNotNull($location);
        $this->assertStringContainsString('/verify/', $location);

        $fakeSmsRepository = self::getContainer()->get(FakeSmsRepository::class);
        $allSms = $fakeSmsRepository->findBy(['toNumber' => '+33699999999']);
        $this->assertCount(0, $allSms);
    }

    public function testUnknownPhoneDoesNotCreateUser(): void
    {
        $client = static::createClient();

        $crawler = $client->request('GET', '/');
        $form = $crawler->filter('form')->form();
        $form['phone_form[phone]'] = '+33699999999';
        $client->submit($form);

        $userRepository = self::getContainer()->get(UserRepository::class);
        $user = $userRepository->findByPhoneNumber('+33699999999');

        $this->assertNull($user);
    }

    public function testEmptyPhoneShowsError(): void
    {
        $client = static::createClient();

        $crawler = $client->request('GET', '/');
        $form = $crawler->filter('form')->form();
        $form['phone_form[phone]'] = '';
        $client->submit($form);

        $this->assertResponseRedirects('/');
        $client->followRedirect();
        $this->assertSelectorExists('.alert-danger');
    }

    public function testVerifyPageRendersAfterPhoneSubmission(): void
    {
        $client = static::createClient();

        $crawler = $client->request('GET', '/');
        $form = $crawler->filter('form')->form();
        $form['phone_form[phone]'] = '+33600000002';
        $client->submit($form);

        $client->followRedirect();

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('h1', 'Vérification');
        $this->assertSelectorExists('input[name="verify_code_form[code]"]');
        $this->assertSelectorExists('input[name="_remember_me"]');
    }
}
