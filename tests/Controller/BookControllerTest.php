<?php

namespace App\Tests\Controller;

use App\Tests\Trait\EntityFactoryTrait;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class BookControllerTest extends WebTestCase
{
    use EntityFactoryTrait;

    public function testEditRequiresAuth(): void
    {
        $client = static::createClient();
        $book = $this->createBook('Auth Test');

        $client->request('GET', '/book/' . $book->getUuid() . '/edit');

        $this->assertResponseRedirects();
    }

    public function testEditLoads(): void
    {
        $client = static::createClient();
        $user = $this->createUser('+33600100001');
        $book = $this->createBook('Mon répertoire');

        $client->loginUser($user);
        $client->request('GET', '/book/' . $book->getUuid() . '/edit');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('h1', 'Liste de contacts');
    }

    public function testEditShowsBookName(): void
    {
        $client = static::createClient();
        $user = $this->createUser('+33600100002');
        $book = $this->createBook('Equipe Alpha');

        $client->loginUser($user);
        $crawler = $client->request('GET', '/book/' . $book->getUuid() . '/edit');

        $nameInput = $crawler->filter('input[name="name"]');
        $this->assertSame('Equipe Alpha', $nameInput->attr('value'));
    }

    public function testEditShowsContacts(): void
    {
        $client = static::createClient();
        $user = $this->createUser('+33600100003');
        $book = $this->createBook('Avec contacts');
        $contact = $this->createContact('+33611000001');
        $book->addContact($contact);
        self::getContainer()->get(\App\Repository\BookRepository::class)->save($book);

        $client->loginUser($user);
        $crawler = $client->request('GET', '/book/' . $book->getUuid() . '/edit');

        $this->assertGreaterThan(0, $crawler->filter('.contact-item')->count());
    }

    public function testEditShowsEmptyContactMessage(): void
    {
        $client = static::createClient();
        $user = $this->createUser('+33600100004');
        $book = $this->createBook('Vide');

        $client->loginUser($user);
        $client->request('GET', '/book/' . $book->getUuid() . '/edit');

        $this->assertSelectorTextContains('.text-muted', 'Aucun contact');
    }

    public function testUpdateName(): void
    {
        $client = static::createClient();
        $user = $this->createUser('+33600100005');
        $book = $this->createBook('Ancien nom');

        $client->loginUser($user);
        $client->request('POST', '/book/' . $book->getUuid() . '/update', [
            'name' => 'Nouveau nom',
        ]);

        $this->assertResponseRedirects('/book/' . $book->getUuid() . '/edit');
        $client->followRedirect();
        $this->assertSelectorTextContains('.alert-success', 'mise à jour');

        $updated = self::getContainer()->get(\App\Repository\BookRepository::class)
            ->findOneBy(['uuid' => $book->getUuid()]);
        $this->assertSame('Nouveau nom', $updated->getName());
    }

    public function testUpdateNameTrimsWhitespace(): void
    {
        $client = static::createClient();
        $user = $this->createUser('+33600100006');
        $book = $this->createBook('Original');

        $client->loginUser($user);
        $client->request('POST', '/book/' . $book->getUuid() . '/update', [
            'name' => '  Trimmed  ',
        ]);

        $this->assertResponseRedirects();

        $updated = self::getContainer()->get(\App\Repository\BookRepository::class)
            ->findOneBy(['uuid' => $book->getUuid()]);
        $this->assertSame('Trimmed', $updated->getName());
    }

    public function testUpdateEmptyNameShowsError(): void
    {
        $client = static::createClient();
        $user = $this->createUser('+33600100007');
        $book = $this->createBook('Ne change pas');

        $client->loginUser($user);
        $client->request('POST', '/book/' . $book->getUuid() . '/update', [
            'name' => '   ',
        ]);

        $this->assertResponseRedirects('/book/' . $book->getUuid() . '/edit');
        $client->followRedirect();
        $this->assertSelectorTextContains('.alert-danger', 'vide');

        $unchanged = self::getContainer()->get(\App\Repository\BookRepository::class)
            ->findOneBy(['uuid' => $book->getUuid()]);
        $this->assertSame('Ne change pas', $unchanged->getName());
    }

    public function testAddContact(): void
    {
        $client = static::createClient();
        $user = $this->createUser('+33600100008');
        $book = $this->createBook('Ajout contact');

        $client->loginUser($user);
        $client->request('POST', '/book/' . $book->getUuid() . '/contact/add', [
            'phone' => '+33677000001',
        ]);

        $this->assertResponseRedirects('/book/' . $book->getUuid() . '/edit');
        $client->followRedirect();
        $this->assertSelectorTextContains('.alert-success', 'ajouté');

        $updated = self::getContainer()->get(\App\Repository\BookRepository::class)
            ->findOneBy(['uuid' => $book->getUuid()]);
        $this->assertCount(1, $updated->getContacts());
    }

    public function testAddContactReusesExisting(): void
    {
        $client = static::createClient();
        $user = $this->createUser('+33600100009');
        $book = $this->createBook('Reuse contact');
        $existing = $this->createContact('+33677000002');

        $client->loginUser($user);
        $client->request('POST', '/book/' . $book->getUuid() . '/contact/add', [
            'phone' => '+33677000002',
        ]);

        $this->assertResponseRedirects();
        $client->followRedirect();
        $this->assertSelectorTextContains('.alert-success', 'ajouté');

        $updated = self::getContainer()->get(\App\Repository\BookRepository::class)
            ->findOneBy(['uuid' => $book->getUuid()]);
        $this->assertCount(1, $updated->getContacts());
        $this->assertSame($existing->getUuid(), $updated->getContacts()->first()->getUuid());
    }

    public function testAddDuplicateContactShowsError(): void
    {
        $client = static::createClient();
        $user = $this->createUser('+33600100010');
        $book = $this->createBook('Doublon');
        $contact = $this->createContact('+33677000003');
        $book->addContact($contact);
        self::getContainer()->get(\App\Repository\BookRepository::class)->save($book);

        $client->loginUser($user);
        $client->request('POST', '/book/' . $book->getUuid() . '/contact/add', [
            'phone' => '+33677000003',
        ]);

        $this->assertResponseRedirects('/book/' . $book->getUuid() . '/edit');
        $client->followRedirect();
        $this->assertSelectorTextContains('.alert-danger', 'déjà dans la liste');
    }

    public function testAddContactEmptyPhoneShowsError(): void
    {
        $client = static::createClient();
        $user = $this->createUser('+33600100011');
        $book = $this->createBook('Vide tel');

        $client->loginUser($user);
        $client->request('POST', '/book/' . $book->getUuid() . '/contact/add', [
            'phone' => '  ',
        ]);

        $this->assertResponseRedirects('/book/' . $book->getUuid() . '/edit');
        $client->followRedirect();
        $this->assertSelectorTextContains('.alert-danger', 'invalide');
    }

    public function testRemoveContact(): void
    {
        $client = static::createClient();
        $user = $this->createUser('+33600100012');
        $book = $this->createBook('Retrait');
        $contact = $this->createContact('+33677000004');
        $book->addContact($contact);
        self::getContainer()->get(\App\Repository\BookRepository::class)->save($book);

        $client->loginUser($user);
        $client->request('POST', '/book/' . $book->getUuid() . '/contact/' . $contact->getUuid() . '/remove');

        $this->assertResponseRedirects('/book/' . $book->getUuid() . '/edit');
        $client->followRedirect();
        $this->assertSelectorTextContains('.alert-success', 'retiré');

        $updated = self::getContainer()->get(\App\Repository\BookRepository::class)
            ->findOneBy(['uuid' => $book->getUuid()]);
        $this->assertCount(0, $updated->getContacts());
    }

    public function testRemoveContactUnknownUuidShowsError(): void
    {
        $client = static::createClient();
        $user = $this->createUser('+33600100013');
        $book = $this->createBook('Introuvable');

        $client->loginUser($user);
        $client->request('POST', '/book/' . $book->getUuid() . '/contact/00000000-0000-0000-0000-000000000000/remove');

        $this->assertResponseRedirects('/book/' . $book->getUuid() . '/edit');
        $client->followRedirect();
        $this->assertSelectorTextContains('.alert-danger', 'introuvable');
    }

    public function testDeleteBook(): void
    {
        $client = static::createClient();
        $user = $this->createUser('+33600100014');
        $book = $this->createBook('A supprimer');

        $client->loginUser($user);
        $client->request('POST', '/book/' . $book->getUuid() . '/delete');

        $this->assertResponseRedirects('/trigger/create');
        $client->followRedirect();
        $this->assertSelectorTextContains('.alert-success', 'supprimé');

        $deleted = self::getContainer()->get(\App\Repository\BookRepository::class)
            ->findOneBy(['uuid' => $book->getUuid()]);
        $this->assertNull($deleted);
    }

    public function testDeleteBookWithContacts(): void
    {
        $client = static::createClient();
        $user = $this->createUser('+33600100015');
        $book = $this->createBook('Avec contacts supprime');
        $contact = $this->createContact('+33677000005');
        $book->addContact($contact);
        self::getContainer()->get(\App\Repository\BookRepository::class)->save($book);

        $client->loginUser($user);
        $client->request('POST', '/book/' . $book->getUuid() . '/delete');

        $this->assertResponseRedirects('/trigger/create');

        $deleted = self::getContainer()->get(\App\Repository\BookRepository::class)
            ->findOneBy(['uuid' => $book->getUuid()]);
        $this->assertNull($deleted);

        // Contact should still exist (not cascade-deleted)
        $contactStill = self::getContainer()->get(\App\Repository\ContactRepository::class)
            ->findByPhoneNumber('+33677000005');
        $this->assertNotNull($contactStill);
    }
}
