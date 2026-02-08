<?php

namespace App\Tests\Controller;

use App\Entity\Book;
use App\Entity\Trigger;
use App\Repository\BookRepository;
use App\Repository\ContactRepository;
use App\Repository\TriggerRepository;
use App\Tests\Trait\EntityFactoryTrait;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class TriggerControllerTest extends WebTestCase
{
    use EntityFactoryTrait;

    public function testCreateRequiresAuth(): void
    {
        $client = static::createClient();

        $client->request('GET', '/trigger/create');

        $this->assertResponseRedirects();
    }

    public function testCreatePageLoads(): void
    {
        $client = static::createClient();
        $user = $this->createUser('+33600200001');

        $client->loginUser($user);
        $client->request('GET', '/trigger/create');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('h1', 'Nouveau déclenchement');
    }

    public function testSubmitWithPhones(): void
    {
        $client = static::createClient();
        $user = $this->createUser('+33600200002');

        $client->loginUser($user);
        $client->request('GET', '/trigger/create');

        $client->submitForm('Créer le déclenchement', [
            'trigger_create_form[type]' => Trigger::TYPE_SMS,
            'trigger_create_form[content]' => 'Bonjour, test de SMS.',
            'trigger_create_form[phones]' => "+33611200001\n+33611200002",
        ]);

        $this->assertResponseRedirects('/');

        $triggers = self::getContainer()->get(TriggerRepository::class)->findByUser($user);
        $this->assertCount(1, $triggers);
        $this->assertSame(Trigger::TYPE_SMS, $triggers[0]->getType());
        $this->assertCount(2, $triggers[0]->getContacts());
    }

    public function testSubmitWithBooks(): void
    {
        $client = static::createClient();
        $user = $this->createUser('+33600200003');
        $contact1 = $this->createContact('+33611200003');
        $contact2 = $this->createContact('+33611200004');
        $book = $this->createBook('Test Book Trigger');
        $book->addContact($contact1);
        $book->addContact($contact2);
        self::getContainer()->get(BookRepository::class)->save($book);

        $client->loginUser($user);
        $crawler = $client->request('GET', '/trigger/create');

        // Find the book checkbox index by matching its value to our book UUID
        $bookUuid = $book->getUuid();
        $checkboxes = $crawler->filter('input[name^="trigger_create_form[books]"][type="checkbox"]');
        $fieldIndex = null;
        $checkboxes->each(function ($node, $i) use ($bookUuid, &$fieldIndex): void {
            if ($node->attr('value') === $bookUuid) {
                $fieldIndex = $i;
            }
        });
        $this->assertNotNull($fieldIndex, 'Book checkbox should be present');

        $form = $crawler->selectButton('Créer le déclenchement')->form();
        $form['trigger_create_form[type]'] = Trigger::TYPE_SMS;
        $form['trigger_create_form[content]'] = 'Message pour le répertoire.';
        $form['trigger_create_form[books][' . $fieldIndex . ']']->tick();

        $client->submit($form);

        $this->assertResponseRedirects('/');

        $triggers = self::getContainer()->get(TriggerRepository::class)->findByUser($user);
        $this->assertCount(1, $triggers);
        $this->assertCount(2, $triggers[0]->getContacts());
    }

    public function testSubmitWithSaveAsBook(): void
    {
        $client = static::createClient();
        $user = $this->createUser('+33600200004');

        $client->loginUser($user);
        $client->request('GET', '/trigger/create');

        $client->submitForm('Créer le déclenchement', [
            'trigger_create_form[type]' => Trigger::TYPE_SMS,
            'trigger_create_form[content]' => 'Test avec sauvegarde répertoire.',
            'trigger_create_form[phones]' => "+33611200005\n+33611200006",
            'trigger_create_form[saveAsBook]' => true,
            'trigger_create_form[bookName]' => 'Mon nouveau répertoire',
        ]);

        $this->assertResponseRedirects('/');

        $book = self::getContainer()->get(BookRepository::class)
            ->findOneBy(['name' => 'Mon nouveau répertoire']);
        $this->assertNotNull($book);
        $this->assertCount(2, $book->getContacts());
    }

    public function testSubmitEmptyContentShowsError(): void
    {
        $client = static::createClient();
        $user = $this->createUser('+33600200005');

        $client->loginUser($user);
        $client->request('GET', '/trigger/create');

        $client->submitForm('Créer le déclenchement', [
            'trigger_create_form[type]' => Trigger::TYPE_SMS,
            'trigger_create_form[content]' => '',
            'trigger_create_form[phones]' => '+33611200007',
        ]);

        $this->assertSelectorTextContains('.alert-danger', 'vide');

        $triggers = self::getContainer()->get(TriggerRepository::class)->findByUser($user);
        $this->assertCount(0, $triggers);
    }

    public function testSubmitNoContactsShowsError(): void
    {
        $client = static::createClient();
        $user = $this->createUser('+33600200006');

        $client->loginUser($user);
        $client->request('GET', '/trigger/create');

        $client->submitForm('Créer le déclenchement', [
            'trigger_create_form[type]' => Trigger::TYPE_SMS,
            'trigger_create_form[content]' => 'Un message sans contacts.',
        ]);

        $this->assertSelectorTextContains('.alert-danger', 'contact');

        $triggers = self::getContainer()->get(TriggerRepository::class)->findByUser($user);
        $this->assertCount(0, $triggers);
    }

    public function testSubmitReusesExistingContact(): void
    {
        $client = static::createClient();
        $user = $this->createUser('+33600200007');
        $existing = $this->createContact('+33611200008');

        $client->loginUser($user);
        $client->request('GET', '/trigger/create');

        $client->submitForm('Créer le déclenchement', [
            'trigger_create_form[type]' => Trigger::TYPE_SMS,
            'trigger_create_form[content]' => 'Test réutilisation contact.',
            'trigger_create_form[phones]' => '+33611200008',
        ]);

        $this->assertResponseRedirects('/');

        $triggers = self::getContainer()->get(TriggerRepository::class)->findByUser($user);
        $this->assertCount(1, $triggers);
        $this->assertSame(
            $existing->getUuid(),
            $triggers[0]->getContacts()->first()->getUuid()
        );
    }

    public function testSubmitCallType(): void
    {
        $client = static::createClient();
        $user = $this->createUser('+33600200008');

        $client->loginUser($user);
        $client->request('GET', '/trigger/create');

        $client->submitForm('Créer le déclenchement', [
            'trigger_create_form[type]' => Trigger::TYPE_CALL,
            'trigger_create_form[content]' => 'Alerte vocale de test.',
            'trigger_create_form[phones]' => '+33611200009',
        ]);

        $this->assertResponseRedirects('/');

        $triggers = self::getContainer()->get(TriggerRepository::class)->findByUser($user);
        $this->assertCount(1, $triggers);
        $this->assertSame(Trigger::TYPE_CALL, $triggers[0]->getType());
    }

    public function testSubmitDeduplicatesPhones(): void
    {
        $client = static::createClient();
        $user = $this->createUser('+33600200009');

        $client->loginUser($user);
        $client->request('GET', '/trigger/create');

        $client->submitForm('Créer le déclenchement', [
            'trigger_create_form[type]' => Trigger::TYPE_SMS,
            'trigger_create_form[content]' => 'Test déduplication.',
            'trigger_create_form[phones]' => "+33611200010\n+33611200010",
        ]);

        $this->assertResponseRedirects('/');

        $triggers = self::getContainer()->get(TriggerRepository::class)->findByUser($user);
        $this->assertCount(1, $triggers);
        $this->assertCount(1, $triggers[0]->getContacts());
    }
}
