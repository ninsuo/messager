<?php

namespace App\Tests\Controller;

use App\Entity\User;
use App\Repository\UserRepository;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Uid\Uuid;

class HomeControllerTest extends WebTestCase
{
    public function testPageLoads(): void
    {
        $client = static::createClient();
        $client->request('GET', '/');

        $this->assertResponseIsSuccessful();
    }

    public function testTitle(): void
    {
        $client = static::createClient();
        $client->request('GET', '/');

        $this->assertSelectorTextContains('title', 'Messager - Connexion');
    }

    public function testHtmlLangIsFrench(): void
    {
        $client = static::createClient();
        $crawler = $client->request('GET', '/');

        $this->assertSame('fr', $crawler->filter('html')->attr('lang'));
    }

    public function testNavbarBrand(): void
    {
        $client = static::createClient();
        $crawler = $client->request('GET', '/');

        $brand = $crawler->filter('.navbar-brand');
        $this->assertCount(1, $brand);
        $this->assertStringContainsString('messager', $brand->text());
        $this->assertSame('/', $brand->attr('href'));
    }

    public function testFavicon(): void
    {
        $client = static::createClient();
        $crawler = $client->request('GET', '/');

        $icon = $crawler->filter('link[rel="icon"]');
        $this->assertCount(1, $icon);
        $this->assertStringContainsString('favicon.svg', $icon->attr('href'));
    }

    public function testLogo(): void
    {
        $client = static::createClient();
        $crawler = $client->request('GET', '/');

        $logo = $crawler->filter('.logo-section img');
        $this->assertCount(1, $logo);
        $this->assertStringContainsString('logo.svg', $logo->attr('src'));
        $this->assertSame('Messager', $logo->attr('alt'));
    }

    public function testLoginHeading(): void
    {
        $client = static::createClient();
        $client->request('GET', '/');

        $this->assertSelectorTextContains('h1', 'Connexion');
    }

    public function testPhoneInput(): void
    {
        $client = static::createClient();
        $crawler = $client->request('GET', '/');

        $input = $crawler->filter('input[type="tel"]');
        $this->assertCount(1, $input);
        $this->assertSame('phone_form[phone]', $input->attr('name'));
        $this->assertNotNull($input->attr('required'));
    }

    public function testSubmitButton(): void
    {
        $client = static::createClient();
        $crawler = $client->request('GET', '/');

        $button = $crawler->filter('button[type="submit"]');
        $this->assertCount(1, $button);
        $this->assertStringContainsString('Continuer', $button->text());
    }

    public function testFormAction(): void
    {
        $client = static::createClient();
        $crawler = $client->request('GET', '/');

        $form = $crawler->filter('form');
        $this->assertCount(1, $form);
        $this->assertSame('post', $form->attr('method'));
        $this->assertSame('/auth', $form->attr('action'));
    }

    public function testFooter(): void
    {
        $client = static::createClient();
        $crawler = $client->request('GET', '/');

        $footer = $crawler->filter('.footer-ms');
        $this->assertCount(1, $footer);
        $this->assertStringContainsString('Messager', $footer->text());
        $this->assertStringContainsString(date('Y'), $footer->text());
    }

    public function testAuthenticatedShowsHelloWorld(): void
    {
        $client = static::createClient();

        $user = new User();
        $user->setUuid(Uuid::v4()->toRfc4122());
        $user->setPhoneNumber('+33600000099');

        $userRepository = self::getContainer()->get(UserRepository::class);
        $userRepository->save($user);

        $client->loginUser($user);
        $client->request('GET', '/');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('h1', 'Hello World');
        $this->assertSelectorTextContains('title', 'Messager');
    }

    public function testAuthenticatedShowsLogoutLink(): void
    {
        $client = static::createClient();

        $user = new User();
        $user->setUuid(Uuid::v4()->toRfc4122());
        $user->setPhoneNumber('+33600000099');

        $userRepository = self::getContainer()->get(UserRepository::class);
        $userRepository->save($user);

        $client->loginUser($user);
        $crawler = $client->request('GET', '/');

        $logoutLink = $crawler->filter('a[href="/logout"]');
        $this->assertCount(1, $logoutLink);
        $this->assertStringContainsString('Déconnexion', $logoutLink->text());
    }

    public function testAuthenticatedDoesNotShowPhoneForm(): void
    {
        $client = static::createClient();

        $user = new User();
        $user->setUuid(Uuid::v4()->toRfc4122());
        $user->setPhoneNumber('+33600000099');

        $userRepository = self::getContainer()->get(UserRepository::class);
        $userRepository->save($user);

        $client->loginUser($user);
        $crawler = $client->request('GET', '/');

        $this->assertCount(0, $crawler->filter('input[type="tel"]'));
    }
}
