<?php

namespace App\Tests\Controller;

use App\Repository\BookRepository;
use App\Repository\ContactRepository;
use App\Tests\Trait\EntityFactoryTrait;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class BookControllerTest extends WebTestCase
{
    use EntityFactoryTrait;

    public function testIndexRequiresAuth(): void
    {
        $client = static::createClient();

        $client->request('GET', '/book');

        $this->assertResponseRedirects();
    }

    public function testIndexLoads(): void
    {
        $client = static::createClient();
        $user = $this->createUser('+33600200001');

        $client->loginUser($user);
        $client->request('GET', '/book');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('h1', 'Listes de contacts');
    }

    public function testIndexShowsBooks(): void
    {
        $client = static::createClient();
        $user = $this->createUser('+33600200002');
        $this->createBook('Liste Alpha');

        $client->loginUser($user);
        $crawler = $client->request('GET', '/book');

        $this->assertResponseIsSuccessful();
        $this->assertStringContainsString('Liste Alpha', $crawler->text());
    }

    public function testCreateBook(): void
    {
        $client = static::createClient();
        $user = $this->createUser('+33600200003');

        $client->loginUser($user);
        $client->request('POST', '/book/create', [
            'name' => 'Nouvelle liste',
        ]);

        $this->assertResponseRedirects('/book');
        $client->followRedirect();
        $this->assertSelectorTextContains('.alert-success', 'créée');

        $created = self::getContainer()->get(BookRepository::class)
            ->findOneBy(['name' => 'Nouvelle liste']);
        $this->assertNotNull($created);
    }

    public function testCreateBookEmptyNameShowsError(): void
    {
        $client = static::createClient();
        $user = $this->createUser('+33600200004');

        $client->loginUser($user);
        $client->request('POST', '/book/create', [
            'name' => '   ',
        ]);

        $this->assertResponseRedirects('/book');
        $client->followRedirect();
        $this->assertSelectorTextContains('.alert-danger', 'vide');
    }

    public function testCreateBookTrimsWhitespace(): void
    {
        $client = static::createClient();
        $user = $this->createUser('+33600200005');

        $client->loginUser($user);
        $client->request('POST', '/book/create', [
            'name' => '  Trimmed List  ',
        ]);

        $this->assertResponseRedirects('/book');

        $created = self::getContainer()->get(BookRepository::class)
            ->findOneBy(['name' => 'Trimmed List']);
        $this->assertNotNull($created);
    }

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

    public function testEditShowsContactsInTextarea(): void
    {
        $client = static::createClient();
        $user = $this->createUser('+33600100003');
        $book = $this->createBook('Avec contacts');
        $contact = $this->createContact('+33611000001');
        $book->addContact($contact);
        self::getContainer()->get(BookRepository::class)->save($book);

        $client->loginUser($user);
        $crawler = $client->request('GET', '/book/' . $book->getUuid() . '/edit');

        $textarea = $crawler->filter('textarea[name="phones"]');
        $this->assertStringContainsString('+33611000001', $textarea->text());
    }

    public function testEditShowsEmptyTextarea(): void
    {
        $client = static::createClient();
        $user = $this->createUser('+33600100004');
        $book = $this->createBook('Vide');

        $client->loginUser($user);
        $crawler = $client->request('GET', '/book/' . $book->getUuid() . '/edit');

        $textarea = $crawler->filter('textarea[name="phones"]');
        $this->assertSame('', trim($textarea->text()));
    }

    public function testUpdateName(): void
    {
        $client = static::createClient();
        $user = $this->createUser('+33600100005');
        $book = $this->createBook('Ancien nom');

        $client->loginUser($user);
        $client->request('POST', '/book/' . $book->getUuid() . '/update', [
            'name' => 'Nouveau nom',
            'phones' => '',
        ]);

        $this->assertResponseRedirects('/book/' . $book->getUuid() . '/edit');
        $client->followRedirect();
        $this->assertSelectorTextContains('.alert-success', 'mise à jour');

        $updated = self::getContainer()->get(BookRepository::class)
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
            'phones' => '',
        ]);

        $this->assertResponseRedirects();

        $updated = self::getContainer()->get(BookRepository::class)
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
            'phones' => '',
        ]);

        $this->assertResponseRedirects('/book/' . $book->getUuid() . '/edit');
        $client->followRedirect();
        $this->assertSelectorTextContains('.alert-danger', 'vide');

        $unchanged = self::getContainer()->get(BookRepository::class)
            ->findOneBy(['uuid' => $book->getUuid()]);
        $this->assertSame('Ne change pas', $unchanged->getName());
    }

    public function testUpdateAddsContacts(): void
    {
        $client = static::createClient();
        $user = $this->createUser('+33600100008');
        $book = $this->createBook('Ajout contacts');

        $client->loginUser($user);
        $client->request('POST', '/book/' . $book->getUuid() . '/update', [
            'name' => 'Ajout contacts',
            'phones' => "+33677000001\n+33677000002",
        ]);

        $this->assertResponseRedirects('/book/' . $book->getUuid() . '/edit');

        $updated = self::getContainer()->get(BookRepository::class)
            ->findOneBy(['uuid' => $book->getUuid()]);
        $this->assertCount(2, $updated->getContacts());
    }

    public function testUpdateRemovesContacts(): void
    {
        $client = static::createClient();
        $user = $this->createUser('+33600100009');
        $book = $this->createBook('Retrait');
        $contact = $this->createContact('+33677000003');
        $book->addContact($contact);
        self::getContainer()->get(BookRepository::class)->save($book);

        $client->loginUser($user);
        $client->request('POST', '/book/' . $book->getUuid() . '/update', [
            'name' => 'Retrait',
            'phones' => '',
        ]);

        $this->assertResponseRedirects();

        $updated = self::getContainer()->get(BookRepository::class)
            ->findOneBy(['uuid' => $book->getUuid()]);
        $this->assertCount(0, $updated->getContacts());
    }

    public function testUpdateReusesExistingContacts(): void
    {
        $client = static::createClient();
        $user = $this->createUser('+33600100010');
        $book = $this->createBook('Reuse');
        $existing = $this->createContact('+33677000004');

        $client->loginUser($user);
        $client->request('POST', '/book/' . $book->getUuid() . '/update', [
            'name' => 'Reuse',
            'phones' => '+33677000004',
        ]);

        $this->assertResponseRedirects();

        $updated = self::getContainer()->get(BookRepository::class)
            ->findOneBy(['uuid' => $book->getUuid()]);
        $this->assertCount(1, $updated->getContacts());
        $this->assertSame($existing->getUuid(), $updated->getContacts()->first()->getUuid());
    }

    public function testUpdateIgnoresInvalidPhones(): void
    {
        $client = static::createClient();
        $user = $this->createUser('+33600100011');
        $book = $this->createBook('Invalides');

        $client->loginUser($user);
        $client->request('POST', '/book/' . $book->getUuid() . '/update', [
            'name' => 'Invalides',
            'phones' => "+33677000005\nnot-a-phone\n+33677000006",
        ]);

        $this->assertResponseRedirects('/book/' . $book->getUuid() . '/edit');
        $client->followRedirect();
        $this->assertSelectorTextContains('.alert-danger', 'not-a-phone');
        $this->assertSelectorTextContains('.alert-success', 'mise à jour');

        $updated = self::getContainer()->get(BookRepository::class)
            ->findOneBy(['uuid' => $book->getUuid()]);
        $this->assertCount(2, $updated->getContacts());
    }

    public function testUpdateDeduplicatesPhones(): void
    {
        $client = static::createClient();
        $user = $this->createUser('+33600100012');
        $book = $this->createBook('Doublons');

        $client->loginUser($user);
        $client->request('POST', '/book/' . $book->getUuid() . '/update', [
            'name' => 'Doublons',
            'phones' => "+33677000007\n+33677000007\n06 77 00 00 07",
        ]);

        $this->assertResponseRedirects();

        $updated = self::getContainer()->get(BookRepository::class)
            ->findOneBy(['uuid' => $book->getUuid()]);
        $this->assertCount(1, $updated->getContacts());
    }

    public function testUpdateNormalizesFormats(): void
    {
        $client = static::createClient();
        $user = $this->createUser('+33600100013');
        $book = $this->createBook('Formats');

        $client->loginUser($user);
        $client->request('POST', '/book/' . $book->getUuid() . '/update', [
            'name' => 'Formats',
            'phones' => "06 12 34 56 78\n0033612345679",
        ]);

        $this->assertResponseRedirects();

        $updated = self::getContainer()->get(BookRepository::class)
            ->findOneBy(['uuid' => $book->getUuid()]);
        $this->assertCount(2, $updated->getContacts());

        $phones = array_map(
            fn ($c) => $c->getPhoneNumber(),
            $updated->getContacts()->toArray()
        );
        sort($phones);
        $this->assertSame(['+33612345678', '+33612345679'], $phones);
    }

    public function testDeleteBook(): void
    {
        $client = static::createClient();
        $user = $this->createUser('+33600100014');
        $book = $this->createBook('A supprimer');

        $client->loginUser($user);
        $client->request('POST', '/book/' . $book->getUuid() . '/delete');

        $this->assertResponseRedirects('/book');
        $client->followRedirect();
        $this->assertSelectorTextContains('.alert-success', 'supprimé');

        $deleted = self::getContainer()->get(BookRepository::class)
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
        self::getContainer()->get(BookRepository::class)->save($book);

        $client->loginUser($user);
        $client->request('POST', '/book/' . $book->getUuid() . '/delete');

        $this->assertResponseRedirects('/book');

        $deleted = self::getContainer()->get(BookRepository::class)
            ->findOneBy(['uuid' => $book->getUuid()]);
        $this->assertNull($deleted);

        // Contact should still exist (not cascade-deleted)
        $contactStill = self::getContainer()->get(ContactRepository::class)
            ->findByPhoneNumber('+33677000005');
        $this->assertNotNull($contactStill);
    }
}
