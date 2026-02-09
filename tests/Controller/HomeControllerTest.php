<?php

namespace App\Tests\Controller;

use App\Entity\Message;
use App\Entity\Trigger;
use App\Entity\User;
use App\Repository\UserRepository;
use App\Tests\Trait\EntityFactoryTrait;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Uid\Uuid;

class HomeControllerTest extends WebTestCase
{
    use EntityFactoryTrait;

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

    public function testAuthenticatedShowsTriggerList(): void
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
        $this->assertSelectorTextContains('h1', 'Déclenchements');
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

    public function testTriggerStatusReturns200ForOwner(): void
    {
        $client = static::createClient();

        $user = $this->createUser('+33600000080');
        $contact = $this->createContact('+33611000080');
        $trigger = $this->createTrigger($user, Trigger::TYPE_SMS, 'Status test');
        $message = $this->createMessage($trigger, $contact);
        $message->setStatus(Message::STATUS_DELIVERED);
        self::getContainer()->get(\App\Repository\MessageRepository::class)->save($message);

        $client->loginUser($user);
        $client->request('GET', '/trigger/' . $trigger->getUuid() . '/status');

        $this->assertResponseIsSuccessful();
    }

    public function testTriggerStatusReturns403ForNonOwner(): void
    {
        $client = static::createClient();

        $owner = $this->createUser('+33600000081');
        $otherUser = $this->createUser('+33600000082');
        $trigger = $this->createTrigger($owner, Trigger::TYPE_SMS, 'Owned trigger');

        $client->loginUser($otherUser);
        $client->request('GET', '/trigger/' . $trigger->getUuid() . '/status');

        $this->assertResponseStatusCodeSame(403);
    }

    public function testTriggerStatusContainsProgressBar(): void
    {
        $client = static::createClient();

        $user = $this->createUser('+33600000083');
        $contact = $this->createContact('+33611000083');
        $trigger = $this->createTrigger($user, Trigger::TYPE_SMS, 'Progress test');
        $message = $this->createMessage($trigger, $contact);
        $message->setStatus(Message::STATUS_DELIVERED);
        self::getContainer()->get(\App\Repository\MessageRepository::class)->save($message);

        $client->loginUser($user);
        $crawler = $client->request('GET', '/trigger/' . $trigger->getUuid() . '/status');

        $this->assertResponseIsSuccessful();
        $this->assertCount(1, $crawler->filter('.trigger-progress'));
        $this->assertCount(1, $crawler->filter('.progress-bar.bg-success'));
    }

    public function testHomePageShowsProgressBars(): void
    {
        $client = static::createClient();

        $user = $this->createUser('+33600000084');
        $contact = $this->createContact('+33611000084');
        $trigger = $this->createTrigger($user, Trigger::TYPE_SMS, 'Home progress');
        $message = $this->createMessage($trigger, $contact);
        $message->setStatus(Message::STATUS_SENT);
        self::getContainer()->get(\App\Repository\MessageRepository::class)->save($message);

        $client->loginUser($user);
        $crawler = $client->request('GET', '/');

        $this->assertResponseIsSuccessful();
        $this->assertCount(1, $crawler->filter('.trigger-progress'));
    }
}
