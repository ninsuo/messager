<?php

namespace App\Controller;

use App\Entity\Book;
use App\Entity\Contact;
use App\Repository\BookRepository;
use App\Repository\ContactRepository;
use App\Tool\Phone;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Uid\Uuid;

#[IsGranted('ROLE_USER')]
#[Route('/book')]
class BookController extends AbstractController
{
    public function __construct(
        private readonly BookRepository $bookRepository,
        private readonly ContactRepository $contactRepository,
    ) {
    }

    #[Route('', name: 'book_index', methods: ['GET'])]
    public function index(): Response
    {
        return $this->render('book/index.html.twig', [
            'books' => $this->bookRepository->findAll(),
        ]);
    }

    #[Route('/create', name: 'book_create', methods: ['POST'])]
    public function create(Request $request): Response
    {
        $name = trim($request->request->getString('name'));

        if ('' === $name) {
            $this->addFlash('error', 'Le nom de la liste ne peut pas être vide.');

            return $this->redirectToRoute('book_index');
        }

        $book = new Book();
        $book->setUuid(Uuid::v4()->toRfc4122());
        $book->setName($name);
        $this->bookRepository->save($book);

        $this->addFlash('success', 'Liste de contacts créée.');

        return $this->redirectToRoute('book_index');
    }

    #[Route('/{uuid}/edit', name: 'book_edit', methods: ['GET'])]
    public function edit(#[MapEntity(mapping: ['uuid' => 'uuid'])] Book $book): Response
    {
        return $this->render('book/edit.html.twig', [
            'book' => $book,
        ]);
    }

    #[Route('/{uuid}/update', name: 'book_update', methods: ['POST'])]
    public function update(
        #[MapEntity(mapping: ['uuid' => 'uuid'])] Book $book,
        Request $request,
    ): Response {
        $name = trim($request->request->getString('name'));

        if ('' === $name) {
            $this->addFlash('error', 'Le nom de la liste de contacts ne peut pas être vide.');

            return $this->redirectToRoute('book_edit', ['uuid' => $book->getUuid()]);
        }

        $book->setName($name);

        // Sync contacts from textarea
        $phonesRaw = $request->request->getString('phones');
        $invalid = [];
        /** @var array<string, Contact> $desiredContacts */
        $desiredContacts = [];

        foreach (explode("\n", $phonesRaw) as $line) {
            $raw = trim($line);
            if ('' === $raw) {
                continue;
            }

            $phone = Phone::normalize($raw);
            if (null === $phone) {
                $invalid[] = $raw;
                continue;
            }

            if (isset($desiredContacts[$phone])) {
                continue;
            }

            $contact = $this->contactRepository->findByPhoneNumber($phone);
            if (null === $contact) {
                $contact = new Contact();
                $contact->setUuid(Uuid::v4()->toRfc4122());
                $contact->setPhoneNumber($phone);
                $this->contactRepository->save($contact);
            }

            $desiredContacts[$phone] = $contact;
        }

        // Remove contacts no longer in the textarea
        foreach ($book->getContacts()->toArray() as $existing) {
            $phone = $existing->getPhoneNumber();
            if (null === $phone || !isset($desiredContacts[$phone])) {
                $book->removeContact($existing);
            }
        }

        // Add new contacts
        foreach ($desiredContacts as $contact) {
            $book->addContact($contact);
        }

        $this->bookRepository->save($book);

        if ([] !== $invalid) {
            $this->addFlash('error', 'Numéros invalides ignorés : ' . implode(', ', $invalid));
        }

        $this->addFlash('success', 'Liste de contacts mise à jour.');

        return $this->redirectToRoute('book_edit', ['uuid' => $book->getUuid()]);
    }

    #[Route('/{uuid}/delete', name: 'book_delete', methods: ['POST'])]
    public function delete(#[MapEntity(mapping: ['uuid' => 'uuid'])] Book $book): Response
    {
        $this->bookRepository->remove($book);

        $this->addFlash('success', 'Liste de contacts supprimée.');

        return $this->redirectToRoute('book_index');
    }
}
