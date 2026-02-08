<?php

namespace App\Tests\Controller;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

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
        $crawler = $client->request('GET', '/');

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
        $crawler = $client->request('GET', '/');

        $this->assertSelectorTextContains('h1', 'Connexion');
    }

    public function testPhoneInput(): void
    {
        $client = static::createClient();
        $crawler = $client->request('GET', '/');

        $input = $crawler->filter('input#phone');
        $this->assertCount(1, $input);
        $this->assertSame('tel', $input->attr('type'));
        $this->assertSame('phone', $input->attr('name'));
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
        $this->assertSame('/', $form->attr('action'));
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
}
