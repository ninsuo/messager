<?php

namespace App\Controller;

use App\Entity\Book;
use App\Entity\Contact;
use App\Entity\Trigger;
use App\Entity\User;
use App\Form\TriggerCreateFormType;
use App\Message\TriggerMessage;
use App\Repository\BookRepository;
use App\Repository\ContactRepository;
use App\Repository\MessageRepository;
use App\Repository\TriggerRepository;
use App\Tool\GSM;
use App\Tool\Phone;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Uid\Uuid;

#[IsGranted('ROLE_USER')]
class TriggerController extends AbstractController
{
    #[Route('/trigger/create', name: 'trigger_create', methods: ['GET', 'POST'])]
    public function create(
        Request $request,
        BookRepository $bookRepository,
        ContactRepository $contactRepository,
        TriggerRepository $triggerRepository,
        MessageBusInterface $messageBus,
    ): Response {
        $allBooks = $bookRepository->findAll();

        $form = $this->createForm(TriggerCreateFormType::class);
        $form->handleRequest($request);

        /** @var array<string, Book> $booksByUuid */
        $booksByUuid = [];
        foreach ($allBooks as $book) {
            $booksByUuid[$book->getUuid()] = $book;
        }

        if ($form->isSubmitted() && $form->isValid()) {
            $data = $form->getData();

            $content = trim((string) ($data['content'] ?? ''));
            if ('' === $content) {
                $this->addFlash('error', 'Le contenu du message ne peut pas être vide.');

                return $this->render('trigger/create.html.twig', [
                    'form' => $form,
                    'books' => $booksByUuid,
                ]);
            }

            /** @var User $user */
            $user = $this->getUser();

            // Collect contacts from selected books
            /** @var array<string, Contact> $contactsByPhone */
            $contactsByPhone = [];

            /** @var Book[] $selectedBooks */
            $selectedBooks = $data['books'] ?? [];
            foreach ($selectedBooks as $selectedBook) {
                foreach ($selectedBook->getContacts() as $contact) {
                    $phone = $contact->getPhoneNumber();
                    if (null !== $phone) {
                        $contactsByPhone[$phone] = $contact;
                    }
                }
            }

            // Parse phone numbers from textarea
            $phonesRaw = (string) ($data['phones'] ?? '');
            $phoneLines = explode("\n", $phonesRaw);
            foreach ($phoneLines as $line) {
                $raw = trim($line);
                if ('' === $raw) {
                    continue;
                }

                $phone = Phone::normalize($raw);
                if (null === $phone) {
                    continue;
                }

                if (!isset($contactsByPhone[$phone])) {
                    $existing = $contactRepository->findByPhoneNumber($phone);
                    if (null !== $existing) {
                        $contactsByPhone[$phone] = $existing;
                    } else {
                        $contact = new Contact();
                        $contact->setUuid(Uuid::v4()->toRfc4122());
                        $contact->setPhoneNumber($phone);
                        $contactRepository->save($contact);
                        $contactsByPhone[$phone] = $contact;
                    }
                }
            }

            if (0 === count($contactsByPhone)) {
                $this->addFlash('error', 'Veuillez sélectionner au moins un contact.');

                return $this->render('trigger/create.html.twig', [
                    'form' => $form,
                    'books' => $booksByUuid,
                ]);
            }

            // Save as book if requested
            if (!empty($data['saveAsBook'])) {
                $bookName = trim((string) ($data['bookName'] ?? ''));
                if ('' !== $bookName) {
                    $newBook = new Book();
                    $newBook->setUuid(Uuid::v4()->toRfc4122());
                    $newBook->setName($bookName);
                    foreach ($contactsByPhone as $contact) {
                        $newBook->addContact($contact);
                    }
                    $bookRepository->save($newBook);
                }
            }

            // Create the trigger
            $trigger = new Trigger();
            $trigger->setUuid(Uuid::v4()->toRfc4122());
            $trigger->setUser($user);

            $type = (string) ($data['type'] ?? Trigger::TYPE_SMS);
            $trigger->setType($type);

            if (Trigger::TYPE_SMS === $type || Trigger::TYPE_BOTH === $type) {
                $content = GSM::enforceGSMAlphabet($content);
            }

            $trigger->setContent($content);

            foreach ($contactsByPhone as $contact) {
                $trigger->addContact($contact);
            }

            $triggerRepository->save($trigger);

            // Dispatch async message
            $messageBus->dispatch(new TriggerMessage($trigger->getUuid()));

            $this->addFlash('success', 'Déclenchement créé avec succès. Les messages sont en cours d\'envoi.');

            return $this->redirectToRoute('trigger_detail', ['uuid' => $trigger->getUuid()]);
        }

        return $this->render('trigger/create.html.twig', [
            'form' => $form,
            'books' => $booksByUuid,
        ]);
    }

    #[Route('/trigger/{uuid}', name: 'trigger_detail', methods: ['GET'])]
    public function detail(
        #[MapEntity(mapping: ['uuid' => 'uuid'])] Trigger $trigger,
        MessageRepository $messageRepository,
    ): Response {
        /** @var User $user */
        $user = $this->getUser();

        if ($trigger->getUser()?->getId() !== $user->getId()) {
            throw $this->createAccessDeniedException();
        }

        $messages = $messageRepository->findByTrigger($trigger);
        $counts = $messageRepository->getStatusCountsByTrigger($trigger);

        return $this->render('trigger/detail.html.twig', [
            'trigger' => $trigger,
            'messages' => $messages,
            'counts' => $counts,
        ]);
    }

    #[Route('/trigger/{uuid}/delete', name: 'trigger_delete', methods: ['POST'])]
    public function delete(
        #[MapEntity(mapping: ['uuid' => 'uuid'])] Trigger $trigger,
        TriggerRepository $triggerRepository,
        MessageRepository $messageRepository,
    ): Response {
        /** @var User $user */
        $user = $this->getUser();

        if ($trigger->getUser()?->getId() !== $user->getId()) {
            throw $this->createAccessDeniedException();
        }

        $messageRepository->removeByTrigger($trigger);
        $triggerRepository->remove($trigger);

        $this->addFlash('success', 'Déclenchement supprimé.');

        return $this->redirectToRoute('home');
    }

    #[Route('/trigger/{uuid}/messages', name: 'trigger_messages', methods: ['GET'])]
    public function messages(
        #[MapEntity(mapping: ['uuid' => 'uuid'])] Trigger $trigger,
        MessageRepository $messageRepository,
    ): Response {
        /** @var User $user */
        $user = $this->getUser();

        if ($trigger->getUser()?->getId() !== $user->getId()) {
            throw $this->createAccessDeniedException();
        }

        $messages = $messageRepository->findByTrigger($trigger);
        $counts = $messageRepository->getStatusCountsByTrigger($trigger);

        return $this->render('trigger/_detail_body.html.twig', [
            'trigger' => $trigger,
            'messages' => $messages,
            'counts' => $counts,
        ]);
    }
}
